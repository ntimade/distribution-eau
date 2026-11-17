<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Meter;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Reading;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Encaissement : le client verse un montant, en règlement complet ou en avance sur sa dette. Le montant paie
 * d'abord la dette la plus ancienne, puis les mois suivants, sur un ou plusieurs compteurs. Chaque compteur
 * reste séparé, et chaque facture garde le détail de ses paiements.
 *
 * Dette de départ, 3 500 par mois, dans l'ordre de paiement :
 * C-1 août, C-1 septembre, C-2 septembre, C-1 octobre, C-2 octobre (17 500 en tout).
 */
class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Meter $first;

    private Meter $second;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 10:00:00');
        $this->user = $this->account('a@t.test', 'Eau Alpha');
        $this->actingAs($this->user);
        $this->customer = Customer::create(['name' => 'Client A', 'phone' => '+237699000001']);
        $this->first = $this->meter('C-1', $this->customer);
        $this->second = $this->meter('C-2', $this->customer);

        // 10 m³ par mois à 350 : 3 500 par facture.
        foreach (['2026-08' => 110, '2026-09' => 120, '2026-10' => 130] as $period => $index) {
            $this->read($this->first, $period, $index);
        }
        foreach (['2026-09' => 110, '2026-10' => 120] as $period => $index) {
            $this->read($this->second, $period, $index);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $email, string $name): User
    {
        $organisation = Organisation::create(['name' => $name, 'code' => Organisation::generateCode($name), 'currency' => 'XAF', 'price_per_m3' => 350]);

        return $organisation->users()->create(['name' => 'Caissier', 'email' => $email, 'password' => 'secret-pass-1']);
    }

    private function meter(string $number, Customer $customer): Meter
    {
        $meter = Meter::make(['number' => $number, 'initial_index' => 100, 'is_active' => true]);
        $meter->customer_id = $customer->id;
        $meter->save();

        return $meter;
    }

    private function read(Meter $meter, string $period, float $index): void
    {
        $this->post(route('readings.store'), ['meter_id' => $meter->id, 'period' => $period, 'current_index' => $index, 'read_at' => '2026-11-10'])
            ->assertSessionHasNoErrors();
    }

    private function month(Meter $meter, string $period): Reading
    {
        return Reading::where('meter_id', $meter->id)->where('period', $period)->firstOrFail();
    }

    private function pay(float $amount, array $extra = [])
    {
        return $this->post(route('receipts.store', $this->customer), [
            'amount' => $amount,
            'paid_at' => '2026-11-15',
            'method' => 'Mobile Money',
            'reference' => 'MM-778',
        ] + $extra);
    }

    public function test_the_menu_has_the_collections_section(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee(route('receipts.index'), false)->assertSee('Encaissements');
        $this->get(route('receipts.index'))->assertOk()->assertSee('Nouvel encaissement');
        $this->get(route('receipts.create'))->assertOk()->assertSee('Client A')->assertSee('17 500 XAF', false);
        // Deux compteurs endettés : on choisit d'abord le compteur.
        $this->get(route('receipts.create', ['customer' => $this->customer->id]))->assertOk()
            ->assertSee('Quel compteur encaisser ?')->assertSee('10 500 XAF', false)->assertSee('7 000 XAF', false)
            ->assertSee(route('receipts.create', ['customer' => $this->customer->id, 'meter' => $this->first->id]))
            ->assertDontSee('Montant versé');
        // « Tous les compteurs » : la dette totale est proposée, sans cases à cocher.
        $this->get(route('receipts.create', ['customer' => $this->customer->id, 'tous' => 1]))->assertOk()
            ->assertSee('Montant versé')->assertSee('value="17500"', false)->assertDontSee('type="checkbox"', false);
        // Un client à un seul compteur passe directement au montant.
        $seul = Customer::create(['name' => 'Client Seul']);
        $this->read($this->meter('C-7', $seul), '2026-10', 110);
        $this->get(route('receipts.create', ['customer' => $seul->id]))->assertOk()->assertSee('Montant versé')->assertDontSee('Quel compteur encaisser ?');
    }

    // L'exemple de l'utilisateur : une avance paie la dette la plus ancienne d'abord.
    public function test_an_advance_pays_the_oldest_debt_first(): void
    {
        $response = $this->pay(10000);
        $receipt = Receipt::firstOrFail();
        $response->assertRedirect(route('receipts.show', $receipt));

        $this->assertEquals(10000, (float) $receipt->total);
        $this->assertEquals(0, $this->month($this->first, '2026-08')->balance());
        $this->assertEquals(0, $this->month($this->first, '2026-09')->balance());
        $this->assertEquals(500, $this->month($this->second, '2026-09')->balance());
        $this->assertEquals(3500, $this->month($this->first, '2026-10')->balance());
        $this->assertEquals(3500, $this->month($this->second, '2026-10')->balance());
        $this->assertSame(3, $receipt->payments()->count());
        $this->assertSame('Mobile Money', Payment::where('reading_id', $this->month($this->first, '2026-08')->id)->value('method'));

        // Chaque compteur reste séparé, avec son sous-total et son reste dû.
        $groups = $receipt->fresh()->load('payments.reading.meter')->byMeter();
        $this->assertSame(['C-1', 'C-2'], $groups->map(fn ($group) => $group->meter->number)->all());
        $this->assertEquals([7000, 3000], $groups->pluck('subtotal')->all());
        $this->assertEquals([3500, 4000], $groups->pluck('remaining')->all());

        $this->get(route('receipts.show', $receipt))->assertOk()->assertSee($receipt->number())->assertSee('Reste dû : 4 000 XAF', false);
    }

    public function test_paying_the_total_settles_every_month(): void
    {
        $this->pay(17500)->assertSessionHasNoErrors();

        $this->assertSame(0, Reading::unpaid()->count());
        $this->assertEquals(17500, (float) Receipt::firstOrFail()->total);
    }

    public function test_more_than_the_debt_or_nothing_is_refused(): void
    {
        $this->pay(20000)->assertSessionHasErrors(['amount' => 'Le montant versé dépasse la dette (17 500 XAF).']);
        $this->pay(0)->assertSessionHasErrors('amount');
        $this->assertSame(0, Receipt::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_from_a_meter_only_its_debt_is_paid_oldest_first(): void
    {
        $this->pay(5000, ['meter_id' => $this->second->id])->assertSessionHasNoErrors();

        $this->assertEquals(0, $this->month($this->second, '2026-09')->balance());
        $this->assertEquals(2000, $this->month($this->second, '2026-10')->balance());
        $this->assertEquals(3500, $this->month($this->first, '2026-08')->balance());

        // Plus que la dette du compteur : refusé.
        $this->pay(5000, ['meter_id' => $this->second->id])->assertSessionHasErrors('amount');
    }

    public function test_another_customer_debt_is_never_touched(): void
    {
        $other = Customer::create(['name' => 'Client B']);
        $meter = $this->meter('C-9', $other);
        $this->read($meter, '2026-07', 110);

        // Même en forçant le compteur de l'autre client, seule la dette du client A est réglée.
        $this->pay(3500, ['meter_id' => $meter->id])->assertSessionHasNoErrors();
        $this->assertEquals(3500, $this->month($meter, '2026-07')->balance());
        $this->assertEquals(0, $this->month($this->first, '2026-08')->balance());
    }

    public function test_cancelling_the_collection_restores_every_month(): void
    {
        $this->pay(7000)->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();

        $this->delete(route('receipts.destroy', $receipt))->assertRedirect(route('customers.show', $this->customer));
        $this->assertSoftDeleted($receipt);
        $this->assertEquals(3500, $this->month($this->first, '2026-08')->balance());
        $this->assertEquals(3500, $this->month($this->first, '2026-09')->balance());
        $this->assertSame(0, Payment::count());
    }

    public function test_correcting_one_payment_keeps_the_receipt_total_right(): void
    {
        $this->pay(7000)->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();

        $payment = Payment::where('reading_id', $this->month($this->first, '2026-09')->id)->firstOrFail();
        $this->put(route('payments.update', $payment), ['amount' => 2000, 'paid_at' => '2026-11-15'])->assertSessionHasNoErrors();
        $this->assertEquals(5500, (float) $receipt->fresh()->total);

        $this->delete(route('payments.destroy', $payment))->assertSessionHasNoErrors();
        $this->assertEquals(3500, (float) $receipt->fresh()->total);

        $this->delete(route('payments.destroy', Payment::where('reading_id', $this->month($this->first, '2026-08')->id)->firstOrFail()));
        $this->assertSoftDeleted($receipt);
    }

    public function test_each_invoice_shows_the_full_payment_detail(): void
    {
        $august = $this->month($this->first, '2026-08');
        $this->post(route('payments.store', $august), ['amount' => 500, 'paid_at' => '2026-11-01', 'method' => 'Espèces'])->assertSessionHasNoErrors();
        $this->pay(3000)->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();

        $this->get(route('invoices.show', $august))->assertOk()
            ->assertSee('Détail des paiements')->assertSee('01/11/2026')->assertSee('Espèces')
            ->assertSee($receipt->number())->assertSee('MM-778')->assertSee('Total payé sur cette facture');
        $this->get(route('invoices.pdf', $august))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('readings.show', $august))->assertOk()->assertSee($receipt->number());
        $this->get(route('payments.index'))->assertOk()->assertSee($receipt->number());
    }

    public function test_the_receipt_prints_downloads_and_goes_to_whatsapp(): void
    {
        $this->pay(10500)->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();

        $this->get(route('receipts.print', $receipt))->assertOk()->assertSee('Reçu d\'encaissement')
            ->assertSee('Sous-total du compteur C-1')->assertSee('Sous-total du compteur C-2')->assertSee('7 000 XAF', false);
        $pdf = $this->get(route('receipts.pdf', $receipt));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $location = $this->post(route('receipts.message', [$receipt, 'whatsapp']))->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/237699000001', $location);
        $text = rawurldecode(explode('?text=', $location)[1]);
        $this->assertStringContainsString($receipt->number(), $text);
        $this->assertStringContainsString('*Compteur C-1*', $text);
        $this->assertStringContainsString('*Compteur C-2*', $text);
        $this->assertStringContainsString('Total encaissé : 10 500 XAF', str_replace("\u{202F}", ' ', $text));
        $this->assertNotNull($receipt->fresh()->whatsapp_opened_at);

        $sms = rawurldecode($this->post(route('receipts.message', [$receipt, 'sms']))->headers->get('Location'));
        $this->assertStringContainsString($receipt->number(), $sms);
    }

    public function test_the_customer_sees_his_debts_meter_by_meter(): void
    {
        $this->get(route('customers.show', $this->customer))->assertOk()
            ->assertSee('Dettes par compteur')->assertSee('Encaisser plusieurs mois')->assertSee('Encaisser ce compteur')
            ->assertSee('10 500 XAF', false)->assertSee('7 000 XAF', false);

        // Depuis un compteur, seule sa dette est proposée.
        $this->get(route('receipts.create', ['customer' => $this->customer->id, 'meter' => $this->second->id]))->assertOk()
            ->assertSee('Compteur C-2')->assertDontSee('Compteur C-1</h2>', false)->assertSee('value="7000"', false);
        $this->get(route('meters.show', $this->first))->assertOk()->assertSee('Encaisser plusieurs mois');

        $this->pay(3500)->assertSessionHasNoErrors();
        $this->get(route('customers.show', $this->customer))->assertSee(Receipt::firstOrFail()->number());
        $this->get(route('receipts.index', ['customer_id' => $this->customer->id]))->assertOk()->assertSee('1 encaissement(s)');
    }

    public function test_readers_and_other_organisations_have_no_access(): void
    {
        $this->pay(3500)->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();

        $reader = $this->user->organisation->users()->create(['name' => 'R', 'email' => 'r@t.test', 'password' => 'secret-pass-1']);
        $reader->forceFill(['role' => User::ROLE_READER])->save();
        $this->actingAs($reader);
        $this->get(route('receipts.index'))->assertForbidden();
        $this->get(route('receipts.show', $receipt))->assertForbidden();

        $this->actingAs($this->account('b@t.test', 'Eau Beta'));
        $this->get(route('receipts.show', $receipt))->assertNotFound();
        $this->post(route('receipts.store', $this->customer), ['amount' => 100, 'paid_at' => '2026-11-15'])->assertNotFound();
    }
}
