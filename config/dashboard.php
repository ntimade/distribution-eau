<?php

// Seuils des alertes du tableau de bord.
return [
    // Factures signalées comme arrivant à échéance dans ce nombre de jours.
    'due_soon_days' => 7,

    // Une consommation est signalée à partir de ce multiple de la moyenne du compteur.
    'consumption_spike_factor' => 2,

    // Nombre de mois d'historique utilisés pour cette moyenne.
    'consumption_history_months' => 6,

    // Une part des impayés est signalée à partir de ce pourcentage.
    'concentration_alert_percent' => 25,

    // Taux de recouvrement et part de clients en règle : « Bon » à partir du premier seuil,
    // « À surveiller » à partir du second, « Critique » en dessous.
    'health_good_percent' => 90,
    'health_warning_percent' => 70,

    // Nombre de zones détaillées dans le graphique circulaire ; les suivantes sont regroupées.
    'ring_zones' => 4,

    // Nombre de consommations inhabituelles affichées, les plus marquées d'abord.
    'anomalies_shown' => 10,
];
