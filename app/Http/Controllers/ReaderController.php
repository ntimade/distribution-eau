<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\User;
use App\Models\Zone;
use App\Services\PasswordRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

// L'administrateur du compte crée ses releveurs d'index et leur affecte une zone ou toutes les zones.
class ReaderController extends Controller
{
    public function index(Request $request): View
    {
        return view('readers.index', [
            'readers' => $this->readers($request)->with('zones')->orderBy('name')->get(),
            'max' => self::max(),
            'code' => $request->user()->organisation->code,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->readers($request)->count() >= self::max()) {
            return redirect()->route('readers.index')->with('error', $this->limitMessage());
        }

        return view('readers.form', ['reader' => null, 'zones' => Zone::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request, null));
        $organisationId = $request->user()->organisation_id;

        // Le verrou évite de dépasser la limite avec deux envois simultanés.
        $reader = DB::transaction(function () use ($data, $organisationId) {
            $organisation = Organisation::whereKey($organisationId)->lockForUpdate()->firstOrFail();

            if ($organisation->users()->where('role', User::ROLE_READER)->count() >= self::max()) {
                return null;
            }

            $reader = $organisation->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $reader->forceFill(['role' => User::ROLE_READER, 'must_change_password' => true])->save();
            $this->saveZones($reader, $data);

            return $reader;
        });

        if (! $reader) {
            return redirect()->route('readers.index')->with('error', $this->limitMessage());
        }

        return redirect()->route('readers.index')->with('success', __('Releveur créé. Donnez-lui le code du compte, son login et son mot de passe : il devra le changer à sa première connexion.'));
    }

    public function edit(Request $request, int $reader): View
    {
        return view('readers.form', [
            'reader' => $this->readers($request)->with('zones')->findOrFail($reader),
            'zones' => Zone::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, int $reader, PasswordRecovery $recovery): RedirectResponse
    {
        $target = $this->readers($request)->findOrFail($reader);
        $data = $request->validate($this->rules($request, $target));

        DB::transaction(function () use ($target, $data) {
            $target->forceFill(['name' => $data['name'], 'email' => $data['email']])->save();
            $this->saveZones($target, $data);
        });

        if (! empty($data['password'])) {
            $recovery->setPassword($target, $data['password'], mustChange: true);
        }

        return redirect()->route('readers.index')->with('success', __('Releveur modifié.'));
    }

    public function destroy(Request $request, int $reader): RedirectResponse
    {
        $target = $this->readers($request)->findOrFail($reader);

        DB::transaction(function () use ($target) {
            DB::table('sessions')->where('user_id', $target->id)->delete();
            // Ses relevés restent : ils appartiennent au compte.
            $target->delete();
        });

        return redirect()->route('readers.index')->with('success', __('Releveur supprimé. Ses relevés sont conservés.'));
    }

    private function readers(Request $request)
    {
        return $request->user()->organisation->users()->where('role', User::ROLE_READER);
    }

    private function rules(Request $request, ?User $reader): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($reader)],
            'access' => ['required', Rule::in(['all', 'zones'])],
            'zone_ids' => ['exclude_unless:access,zones', 'required', 'array'],
            'zone_ids.*' => ['integer', 'distinct', Rule::exists('zones', 'id')->where('organisation_id', $request->user()->organisation_id)],
            'password' => [$reader ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
    }

    private function saveZones(User $reader, array $data): void
    {
        $all = $data['access'] === 'all';

        $reader->forceFill(['all_zones' => $all])->save();
        $reader->zones()->sync($all ? [] : $data['zone_ids']);
    }

    private function limitMessage(): string
    {
        return __('Ce compte a déjà :v1 releveurs, le maximum autorisé.', ['v1' => self::max()]);
    }

    private static function max(): int
    {
        return (int) config('readers.max_per_organisation');
    }
}
