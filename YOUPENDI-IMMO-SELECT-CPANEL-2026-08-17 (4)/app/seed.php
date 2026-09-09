<?php

function seed_if_empty(): void
{
    if ((int) db()->val('SELECT COUNT(*) FROM users') > 0) {
        return;
    }
    seed_demo();
}

function seed_demo(): void
{
    $hash = fn(string $p) => password_hash($p, PASSWORD_DEFAULT);
    $t = now();

    $defaults = [
        'company' => 'YOUPENDI IMMO SELECT',
        'whatsapp' => config('contact.whatsapp'),
        'phone' => config('contact.phone'),
        'email' => config('contact.email'),
        'address' => config('contact.address'),
        'commission_mgmt' => (string) config('commission_defaults.youpendi_mgmt'),
        'commission_apporteur' => (string) config('commission_defaults.agent_apporteur'),
        'commission_commercial' => (string) config('commission_defaults.agent_commercial'),
        'commission_supervisor' => (string) config('commission_defaults.supervisor'),
        'currency' => 'USD',
        'about' => 'YOUPENDI IMMO SELECT est la plateforme de gestion immobilière et d’intermédiation locative de YOUPENDI. Nous accompagnons propriétaires, locataires et voyageurs de la prospection au reversement, sans jamais vendre de parcelles.',
    ];
    foreach ($defaults as $k => $v) {
        db()->insert('settings', ['k' => $k, 'v' => $v, 'updated_at' => $t]);
    }

    $users = [
        ['admin@youpendi.cd', 'Admin@2026', 'Divine', 'Youpendi', '970000001', 'admin'],
        ['finance@youpendi.cd', 'Finance@2026', 'Patrick', 'Mulume', '970000002', 'finance'],
        ['gestion@youpendi.cd', 'Gestion@2026', 'Amina', 'Bahati', '970000003', 'manager'],
        ['superviseur@youpendi.cd', 'Super@2026', 'Jean', 'Kambale', '970000004', 'supervisor'],
        ['agent@youpendi.cd', 'Agent@2026', 'Sarah', 'Furaha', '970000005', 'agent'],
        ['agent2@youpendi.cd', 'Agent@2026', 'David', 'Mapendo', '970000006', 'agent'],
        ['proprio@youpendi.cd', 'Proprio@2026', 'Claude', 'Rwimo', '970000007', 'owner'],
        ['proprio2@youpendi.cd', 'Proprio@2026', 'Esther', 'Kahindo', '970000008', 'owner'],
        ['locataire@youpendi.cd', 'Locataire@2026', 'Grace', 'Nzanzu', '970000009', 'tenant'],
        ['voyageur@youpendi.cd', 'Voyageur@2026', 'Marc', 'Imani', '970000010', 'traveler'],
    ];
    $uids = [];
    foreach ($users as $u) {
        $uids[$u[5]][] = db()->insert('users', [
            'email' => $u[0], 'password' => $hash($u[1]),
            'first_name' => $u[2], 'last_name' => $u[3],
            'phone' => '+243' . $u[4], 'whatsapp' => '243' . $u[4],
            'role' => $u[5], 'status' => 'actif', 'created_at' => $t, 'updated_at' => $t,
        ]);
    }

    $teamId = db()->insert('teams', [
        'name' => 'Équipe Goma', 'city' => 'Goma',
        'supervisor_id' => $uids['supervisor'][0], 'created_at' => $t,
    ]);

    $agentSuper = db()->insert('immo_agents', [
        'user_id' => $uids['supervisor'][0], 'team_id' => $teamId, 'type' => 'senior',
        'commission_apporteur' => 20, 'commission_commercial' => 20, 'created_at' => $t,
    ]);
    $agent1 = db()->insert('immo_agents', [
        'user_id' => $uids['agent'][0], 'team_id' => $teamId, 'supervisor_id' => $agentSuper,
        'type' => 'immobilier', 'created_at' => $t,
    ]);
    $agent2 = db()->insert('immo_agents', [
        'user_id' => $uids['agent'][1], 'team_id' => $teamId, 'supervisor_id' => $agentSuper,
        'type' => 'prospecteur', 'created_at' => $t,
    ]);
    $managerAgent = db()->insert('immo_agents', [
        'user_id' => $uids['manager'][0], 'team_id' => $teamId, 'type' => 'immobilier', 'created_at' => $t,
    ]);

    $owner1 = db()->insert('owners', [
        'reference' => 'PROP-0001', 'user_id' => $uids['owner'][0], 'first_name' => 'Claude', 'last_name' => 'Rwimo',
        'phone' => '+243970000007', 'whatsapp' => '243970000007', 'email' => 'proprio@youpendi.cd',
        'city' => 'Goma', 'address' => 'Himbi, Goma', 'created_at' => $t,
    ]);
    $owner2 = db()->insert('owners', [
        'reference' => 'PROP-0002', 'user_id' => $uids['owner'][1], 'first_name' => 'Esther', 'last_name' => 'Kahindo',
        'phone' => '+243970000008', 'whatsapp' => '243970000008', 'email' => 'proprio2@youpendi.cd',
        'city' => 'Goma', 'address' => 'Keshero, Goma', 'created_at' => $t,
    ]);

    $props = [
        [
            'title' => 'Appartement standing 3 chambres — Himbi',
            'type' => 'appartement', 'city' => 'Goma', 'commune' => 'Goma', 'quartier' => 'Himbi',
            'address' => 'Avenue des Volcans, Himbi', 'address_public' => 'Himbi, proche lac',
            'rent' => 850, 'deposit' => 850, 'bedrooms' => 3, 'bathrooms' => 2, 'area' => 145,
            'furnished' => 1, 'status' => 'disponible', 'owner' => $owner1, 'apporteur' => $agent1,
            'img' => 'p1.jpg', 'usage' => 'residentiel',
            'amenities' => 'climatisation,groupe,eau,internet,parking,securite,cuisine,meuble,balcon,eau_chaude',
            'desc' => 'Bel appartement lumineux au cœur de Himbi, finitions premium, séjour ouvert sur terrasse, cuisine équipée, sécurité 24/24 et groupe électrogène. Idéal pour famille ou expatrié.',
        ],
        [
            'title' => 'Villa contemporaine vue lac — Keshero',
            'type' => 'villa', 'city' => 'Goma', 'commune' => 'Goma', 'quartier' => 'Keshero',
            'address' => 'Boulevard du Lac, Keshero', 'address_public' => 'Keshero, boulevard du lac',
            'rent' => 2200, 'deposit' => 2200, 'bedrooms' => 5, 'bathrooms' => 4, 'area' => 380,
            'furnished' => 1, 'status' => 'disponible', 'owner' => $owner2, 'apporteur' => $agent2,
            'img' => 'p2.jpg', 'usage' => 'residentiel',
            'amenities' => 'climatisation,groupe,eau,internet,parking,securite,jardin,piscine,meuble,vue_lac',
            'desc' => 'Villa d’exception face au lac Kivu. Cinq suites, salon double hauteur, piscine, jardin tropical et dépendance personnel. Prestations haut de gamme.',
        ],
        [
            'title' => 'Studio meublé premium — Katindo',
            'type' => 'studio', 'city' => 'Goma', 'commune' => 'Goma', 'quartier' => 'Katindo',
            'address' => 'Avenue de la Paix, Katindo', 'address_public' => 'Katindo centre',
            'rent' => 350, 'deposit' => 350, 'bedrooms' => 1, 'bathrooms' => 1, 'area' => 38,
            'furnished' => 1, 'status' => 'disponible', 'owner' => $owner1, 'apporteur' => $agent1,
            'img' => 'p3.jpg', 'usage' => 'residentiel',
            'amenities' => 'climatisation,eau,internet,securite,cuisine,meuble,eau_chaude',
            'desc' => 'Studio entièrement meublé, prêt à vivre, fibre, climatisation. Parfait pour jeune cadre ou mission de quelques mois.',
        ],
        [
            'title' => 'Maison familiale 4 chambres — Les Volcans',
            'type' => 'maison', 'city' => 'Goma', 'commune' => 'Karisimbi', 'quartier' => 'Les Volcans',
            'address' => 'Quartier Les Volcans', 'address_public' => 'Les Volcans',
            'rent' => 700, 'deposit' => 700, 'bedrooms' => 4, 'bathrooms' => 2, 'area' => 210,
            'furnished' => 0, 'status' => 'occupe', 'owner' => $owner1, 'apporteur' => $agent1,
            'img' => 'p4.jpg', 'usage' => 'residentiel',
            'amenities' => 'groupe,eau,parking,securite,jardin,cuisine',
            'desc' => 'Maison calme avec jardin, salon vaste, cuisine séparée, proche des écoles. Idéale pour une famille.',
        ],
        [
            'title' => 'Bureaux 120 m² — Gombe / Kinshasa',
            'type' => 'bureau', 'city' => 'Kinshasa', 'commune' => 'Gombe', 'quartier' => 'Gombe',
            'address' => 'Boulevard du 30 juin', 'address_public' => 'Gombe, boulevard principal',
            'rent' => 1800, 'deposit' => 1800, 'bedrooms' => 0, 'bathrooms' => 2, 'area' => 120,
            'furnished' => 1, 'status' => 'disponible', 'owner' => $owner2, 'apporteur' => $agent2,
            'img' => 'p5.jpg', 'usage' => 'professionnel',
            'amenities' => 'climatisation,groupe,eau,internet,parking,securite,ascenseur',
            'desc' => 'Plateau de bureaux climatisé, open space + 2 salles de réunion, accueil, parking sécurisé. Adresse prestigieuse.',
        ],
        [
            'title' => 'Villa 6 chambres — Nguba / Bukavu',
            'type' => 'villa', 'city' => 'Bukavu', 'commune' => 'Ibanda', 'quartier' => 'Nguba',
            'address' => 'Nguba haut', 'address_public' => 'Nguba, Ibanda',
            'rent' => 1500, 'deposit' => 1500, 'bedrooms' => 6, 'bathrooms' => 4, 'area' => 420,
            'furnished' => 0, 'status' => 'visite_en_cours', 'owner' => $owner2, 'apporteur' => $agent1,
            'img' => 'p6.jpg', 'usage' => 'residentiel',
            'amenities' => 'groupe,eau,parking,securite,jardin,vue_lac',
            'desc' => 'Grande villa avec vue partielle sur le lac, dépendances, cour pavée. Parfaite résidence diplomatique ou familiale.',
        ],
        [
            'title' => 'Appartement 2 chambres — Mabanga',
            'type' => 'appartement', 'city' => 'Goma', 'commune' => 'Karisimbi', 'quartier' => 'Mabanga',
            'address' => 'Mabanga Sud', 'address_public' => 'Mabanga Sud',
            'rent' => 280, 'deposit' => 280, 'bedrooms' => 2, 'bathrooms' => 1, 'area' => 72,
            'furnished' => 0, 'status' => 'disponible', 'owner' => $owner1, 'apporteur' => $agent2,
            'img' => 'p7.jpg', 'usage' => 'residentiel',
            'amenities' => 'eau,parking,securite,cuisine',
            'desc' => 'Appartement fonctionnel, bien situé, loyer accessible. Eau de forage et gardiennage.',
        ],
        [
            'title' => 'Local commercial 60 m² — Mikeno',
            'type' => 'local_commercial', 'city' => 'Goma', 'commune' => 'Goma', 'quartier' => 'Mikeno',
            'address' => 'Avenue Mikeno', 'address_public' => 'Mikeno, axe commercial',
            'rent' => 450, 'deposit' => 450, 'bedrooms' => 0, 'bathrooms' => 1, 'area' => 60,
            'furnished' => 0, 'status' => 'disponible', 'owner' => $owner2, 'apporteur' => $agent1,
            'img' => 'p8.jpg', 'usage' => 'professionnel',
            'amenities' => 'eau,securite,parking',
            'desc' => 'Local en rez-de-chaussée, vitrine sur avenue passante. Idéal boutique, agence ou showroom.',
        ],
    ];

    $propIds = [];
    foreach ($props as $i => $p) {
        $pid = db()->insert('properties', [
            'reference' => 'BIE-' . str_pad((string) (1 + $i), 4, '0', STR_PAD_LEFT),
            'title' => $p['title'],
            'slug' => slugify($p['title']),
            'type' => $p['type'],
            'usage_type' => $p['usage'],
            'description' => $p['desc'],
            'city' => $p['city'],
            'commune' => $p['commune'],
            'quartier' => $p['quartier'],
            'address' => $p['address'],
            'address_public' => $p['address_public'],
            'rent' => $p['rent'],
            'currency' => 'USD',
            'deposit' => $p['deposit'],
            'bedrooms' => $p['bedrooms'],
            'bathrooms' => $p['bathrooms'],
            'area' => $p['area'],
            'furnished' => $p['furnished'],
            'amenities' => $p['amenities'],
            'status' => $p['status'],
            'available_from' => today(),
            'owner_id' => $p['owner'],
            'agent_apporteur_id' => $p['apporteur'],
            'agent_commercial_id' => $p['apporteur'],
            'manager_id' => $managerAgent,
            'commission_rate' => 10,
            'managed_from' => '2025-06-01',
            'visit_modalities' => 'Sur rendez-vous, 7j/7. Pièce d’identité obligatoire.',
            'is_short_stay' => 0,
            'created_by' => $uids['admin'][0],
            'created_at' => $t,
            'updated_at' => $t,
        ]);
        $propIds[] = $pid;
        db()->insert('property_photos', [
            'property_id' => $pid,
            'path' => '../assets/img/' . $p['img'],
            'is_cover' => 1,
            'sort_order' => 0,
        ]);
        // extra photos
        foreach (['p6.jpg', 'p4.jpg'] as $k => $extra) {
            db()->insert('property_photos', [
                'property_id' => $pid,
                'path' => '../assets/img/' . $extra,
                'is_cover' => 0,
                'sort_order' => $k + 1,
            ]);
        }
    }

    // Occupied house -> tenant + contract + rents
    $occupied = $propIds[3];
    $tenantId = db()->insert('tenants', [
        'reference' => 'LOC-0001', 'user_id' => $uids['tenant'][0],
        'first_name' => 'Grace', 'last_name' => 'Nzanzu',
        'phone' => '+243970000009', 'whatsapp' => '243970000009',
        'email' => 'locataire@youpendi.cd', 'property_id' => $occupied,
        'entry_date' => '2025-07-01', 'created_at' => $t,
    ]);
    $cid = db()->insert('contracts', [
        'reference' => 'CTR-0001',
        'property_id' => $occupied, 'owner_id' => $owner1, 'tenant_id' => $tenantId,
        'agent_id' => $agent1, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30',
        'rent' => 700, 'deposit' => 700, 'currency' => 'USD', 'periodicity' => 'mensuel',
        'commission_rate' => 10, 'conditions' => 'Bail d’habitation d’un an renouvelable. Charges : eau et électricité à la charge du locataire.',
        'status' => 'actif', 'created_at' => $t,
    ]);
    db()->update('tenants', ['contract_id' => $cid], 'id = ?', [$tenantId]);

    $months = [
        ['2025-07-01', '2025-07-31', '2025-07-05', 'paye', 700],
        ['2025-08-01', '2025-08-31', '2025-08-05', 'paye', 700],
        ['2025-09-01', '2025-09-30', '2025-09-05', 'paye', 700],
        ['2025-10-01', '2025-10-31', '2025-10-05', 'paye', 700],
        ['2025-11-01', '2025-11-30', '2025-11-05', 'paye', 700],
        ['2025-12-01', '2025-12-31', '2025-12-05', 'paye', 700],
        ['2026-01-01', '2026-01-31', '2026-01-05', 'paye', 700],
        ['2026-02-01', '2026-02-28', '2026-02-05', 'paye', 700],
        ['2026-03-01', '2026-03-31', '2026-03-05', 'paye', 700],
        ['2026-04-01', '2026-04-30', '2026-04-05', 'paye', 700],
        ['2026-05-01', '2026-05-31', '2026-05-05', 'paye', 700],
        ['2026-06-01', '2026-06-30', '2026-06-05', 'a_payer', 0],
        ['2026-07-01', '2026-07-31', '2026-07-05', 'a_venir', 0],
        ['2026-08-01', '2026-08-31', '2026-08-05', 'a_venir', 0],
    ];
    foreach ($months as $m) {
        $rid = db()->insert('rents', [
            'contract_id' => $cid, 'property_id' => $occupied, 'owner_id' => $owner1, 'tenant_id' => $tenantId,
            'period_start' => $m[0], 'period_end' => $m[1], 'due_date' => $m[2],
            'amount' => 700, 'paid_amount' => $m[4], 'currency' => 'USD', 'status' => $m[3], 'created_at' => $t,
        ]);
        if ($m[3] === 'paye') {
            $payId = db()->insert('payments', [
                'rent_id' => $rid, 'amount' => 700, 'method' => 'virement', 'reference' => 'VIR-' . date('Ym', strtotime($m[0])),
                'paid_at' => $m[2], 'status' => 'confirme', 'confirmed_by' => $uids['finance'][0],
                'confirmed_at' => $m[2] . ' 10:00:00', 'created_by' => $uids['finance'][0], 'created_at' => $t,
            ]);
            db()->insert('receipts', [
                'number' => 'Q-' . date('ym', strtotime($m[0])) . '-00' . $rid,
                'payment_id' => $payId, 'rent_id' => $rid, 'tenant_id' => $tenantId, 'property_id' => $occupied,
                'period_label' => date('m/Y', strtotime($m[0])), 'amount' => 700, 'method' => 'virement',
                'issued_at' => $m[2], 'created_at' => $t,
            ]);
            db()->insert('commissions', [
                'type' => 'gestion_youpendi', 'property_id' => $occupied, 'contract_id' => $cid, 'payment_id' => $payId,
                'base_amount' => 700, 'rate' => 10, 'amount' => 70, 'status' => 'validee',
                'validated_by' => $uids['finance'][0], 'validated_at' => $t, 'created_at' => $t,
            ]);
        }
    }

    db()->insert('commissions', [
        'type' => 'apporteur', 'agent_id' => $agent1, 'property_id' => $occupied, 'contract_id' => $cid,
        'base_amount' => 700, 'rate' => 30, 'amount' => 210, 'status' => 'payee', 'paid_at' => '2025-07-15', 'created_at' => $t,
    ]);
    db()->insert('commissions', [
        'type' => 'commercial', 'agent_id' => $agent1, 'property_id' => $occupied, 'contract_id' => $cid,
        'base_amount' => 700, 'rate' => 40, 'amount' => 280, 'status' => 'validee', 'created_at' => $t,
    ]);

    db()->insert('expenses', [
        'property_id' => $occupied, 'category' => 'plomberie', 'amount' => 45, 'expense_date' => '2026-03-12',
        'description' => 'Remplacement robinetterie cuisine', 'beneficiary' => 'Plomberie Kivu',
        'created_by' => $uids['manager'][0], 'validated_by' => $uids['finance'][0], 'status' => 'validee', 'created_at' => $t,
    ]);

    db()->insert('payouts', [
        'owner_id' => $owner1, 'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
        'collections' => 700, 'commission' => 70, 'expenses' => 45, 'net_amount' => 585,
        'status' => 'paye', 'paid_at' => '2026-04-08', 'created_at' => $t,
    ]);
    db()->insert('payouts', [
        'owner_id' => $owner1, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31',
        'collections' => 700, 'commission' => 70, 'expenses' => 0, 'net_amount' => 630,
        'status' => 'a_preparer', 'created_at' => $t,
    ]);

    // Prospects
    $prospects = [
        ['locataire', 'Alain', 'Bisimwa', '243990111222', 'Goma', 'Himbi', 'appartement', 2, 400, 700, $agent1, 'recherche_en_cours'],
        ['locataire', 'Julie', 'Mumbere', '243990111223', 'Goma', 'Keshero', 'villa', 4, 1500, 2500, $agent1, 'nouveau'],
        ['proprietaire', 'Henri', 'Kasereka', '243990111224', 'Goma', 'Katindo', 'maison', 3, 0, 600, $agent2, 'contacte'],
        ['locataire', 'Nadia', 'Safari', '243990111225', 'Bukavu', 'Nguba', 'appartement', 3, 500, 900, $agent2, 'nouveau'],
        ['proprietaire', 'Pascal', 'Lushima', '243990111226', 'Kinshasa', 'Gombe', 'bureau', 0, 0, 2000, $agent1, 'nouveau'],
    ];
    foreach ($prospects as $pr) {
        $pid = db()->insert('prospects', [
            'type' => $pr[0], 'first_name' => $pr[1], 'last_name' => $pr[2],
            'phone' => $pr[3], 'whatsapp' => $pr[3], 'city' => $pr[4], 'quartier' => $pr[5],
            'property_type' => $pr[6], 'bedrooms' => $pr[7], 'budget_min' => $pr[8], 'budget_max' => $pr[9],
            'agent_id' => $pr[10], 'status' => $pr[11],
            'source' => $pr[0] === 'proprietaire' ? 'site_je_suis_proprietaire' : 'site_je_cherche_logement',
            'created_at' => $t, 'updated_at' => $t,
        ]);
        db()->insert('prospect_status_history', [
            'prospect_id' => $pid, 'old_status' => null, 'new_status' => $pr[11],
            'user_id' => $uids['agent'][0], 'note' => 'Prospect de démonstration.', 'created_at' => $t,
        ]);
        db()->insert('prospect_notes', [
            'prospect_id' => $pid, 'user_id' => $uids['agent'][0],
            'body' => 'Premier contact établi. Relance prévue cette semaine.', 'created_at' => $t,
        ]);
    }

    db()->insert('visits', [
        'property_id' => $propIds[0], 'visitor_name' => 'Alain Bisimwa', 'visitor_phone' => '243990111222',
        'agent_id' => $agent1, 'scheduled_at' => date('Y-m-d 10:00:00', strtotime('+2 day')),
        'status' => 'planifiee', 'created_at' => $t,
    ]);
    db()->insert('visits', [
        'property_id' => $propIds[1], 'visitor_name' => 'Julie Mumbere', 'visitor_phone' => '243990111223',
        'agent_id' => $agent1, 'scheduled_at' => date('Y-m-d 15:00:00', strtotime('-3 day')),
        'status' => 'realisee', 'report' => 'Cliente très intéressée. Budget compatible. Dossier à constituer.', 'created_at' => $t,
    ]);

    db()->insert('tasks', [
        'agent_id' => $agent1, 'title' => 'Relancer Alain Bisimwa après visite Himbi',
        'due_at' => date('Y-m-d 09:00:00', strtotime('+1 day')), 'status' => 'ouverte', 'created_at' => $t,
    ]);
    db()->insert('tasks', [
        'agent_id' => $agent2, 'title' => 'Visite technique villa Kasereka — Katindo',
        'due_at' => date('Y-m-d 14:00:00', strtotime('+3 day')), 'status' => 'ouverte', 'created_at' => $t,
    ]);

    // Short stay
    $stayProps = [
        [
            'title' => 'Suite Kivu — terrasse coucher de soleil',
            'type' => 'residence_meublee', 'quartier' => 'Keshero', 'rent' => 85,
            'bedrooms' => 1, 'bathrooms' => 1, 'area' => 48, 'img' => 'stay1.jpg',
            'desc' => 'Suite meublée avec terrasse vue lac, linge de maison, kitchenette, check-in autonome. Idéale couple ou mission courte.',
            'guests' => 2, 'beds' => 1, 'price' => 85,
        ],
        [
            'title' => 'Résidence Himbi — 2 chambres',
            'type' => 'residence_meublee', 'quartier' => 'Himbi', 'rent' => 120,
            'bedrooms' => 2, 'bathrooms' => 2, 'area' => 86, 'img' => 'stay2.jpg',
            'desc' => 'Appartement entier, deux chambres, salon, cuisine équipée, fibre, parking. Parfait pour familles et équipes projet.',
            'guests' => 4, 'beds' => 3, 'price' => 120,
        ],
        [
            'title' => 'Studio voyageur — Katindo',
            'type' => 'studio', 'quartier' => 'Katindo', 'rent' => 55,
            'bedrooms' => 1, 'bathrooms' => 1, 'area' => 32, 'img' => 'stay3.jpg',
            'desc' => 'Studio compact, propre, climatisé, à 8 minutes du centre. Petit-déjeuner possible sur demande.',
            'guests' => 2, 'beds' => 1, 'price' => 55,
        ],
    ];
    $stayIds = [];
    foreach ($stayProps as $i => $s) {
        $pid = db()->insert('properties', [
            'reference' => 'ST-' . (101 + $i),
            'title' => $s['title'], 'slug' => slugify($s['title']),
            'type' => $s['type'], 'usage_type' => 'residentiel', 'description' => $s['desc'],
            'city' => 'Goma', 'commune' => 'Goma', 'quartier' => $s['quartier'],
            'address' => $s['quartier'] . ', Goma', 'address_public' => $s['quartier'] . ', Goma',
            'rent' => $s['rent'], 'currency' => 'USD', 'deposit' => 0,
            'bedrooms' => $s['bedrooms'], 'bathrooms' => $s['bathrooms'], 'area' => $s['area'],
            'furnished' => 1, 'amenities' => 'climatisation,eau,internet,securite,cuisine,meuble,eau_chaude,tv',
            'status' => 'disponible', 'owner_id' => $owner2, 'agent_apporteur_id' => $agent1,
            'manager_id' => $managerAgent, 'commission_rate' => 18, 'is_short_stay' => 1,
            'created_by' => $uids['admin'][0], 'created_at' => $t, 'updated_at' => $t,
        ]);
        db()->insert('property_photos', [
            'property_id' => $pid, 'path' => '../assets/img/' . $s['img'], 'is_cover' => 1, 'sort_order' => 0,
        ]);
        $sid = db()->insert('stay_listings', [
            'property_id' => $pid, 'name' => $s['title'], 'guests' => $s['guests'], 'beds' => $s['beds'],
            'price_night' => $s['price'], 'extra_fees' => 10, 'rules' => 'Non-fumeur. Pas de fêtes. Check-in 14h, check-out 11h.',
            'status' => 'disponible',
        ]);
        $stayIds[] = [$sid, $pid, $s];
    }

    $resId = db()->insert('reservations', [
        'number' => 'RS-2608-014',
        'stay_id' => $stayIds[0][0], 'property_id' => $stayIds[0][1],
        'user_id' => $uids['traveler'][0],
        'guest_name' => 'Marc Imani', 'guest_phone' => '+243970000010', 'guest_email' => 'voyageur@youpendi.cd',
        'checkin' => date('Y-m-d', strtotime('+5 day')), 'checkout' => date('Y-m-d', strtotime('+8 day')),
        'nights' => 3, 'guests' => 2, 'price_night' => 85, 'fees' => 10, 'total' => 265, 'paid_amount' => 265,
        'status' => 'confirmee', 'created_at' => $t,
    ]);
    db()->insert('reservations', [
        'number' => 'RS-2607-009',
        'stay_id' => $stayIds[1][0], 'property_id' => $stayIds[1][1],
        'guest_name' => 'ONG Horizon', 'guest_phone' => '+243990000111', 'guest_email' => 'ops@horizon.org',
        'checkin' => date('Y-m-d', strtotime('-10 day')), 'checkout' => date('Y-m-d', strtotime('-6 day')),
        'nights' => 4, 'guests' => 3, 'price_night' => 120, 'fees' => 10, 'total' => 490, 'paid_amount' => 490,
        'status' => 'cloturee', 'created_at' => $t,
    ]);
    db()->insert('housekeeping', [
        'reservation_id' => $resId, 'stay_id' => $stayIds[0][0], 'property_id' => $stayIds[0][1],
        'assigned_to' => 'Service ménage Himbi', 'status' => 'planifie', 'created_at' => $t,
    ]);

    db()->insert('vendors', [
        'name' => 'Plomberie Kivu', 'specialty' => 'plomberie', 'phone' => '+243990200100', 'city' => 'Goma',
    ]);
    db()->insert('vendors', [
        'name' => 'Electro Plus', 'specialty' => 'electricite', 'phone' => '+243990200101', 'city' => 'Goma',
    ]);
    db()->insert('maintenances', [
        'property_id' => $occupied, 'tenant_id' => $tenantId, 'category' => 'electricite',
        'urgency' => 'normale', 'description' => 'Prise du salon qui disjoncte par intermittence.',
        'status' => 'a_analyser', 'created_by' => $uids['tenant'][0], 'created_at' => $t, 'updated_at' => $t,
    ]);

    foreach ([$uids['admin'][0], $uids['agent'][0], $uids['owner'][0], $uids['tenant'][0]] as $uid) {
        notify($uid, 'Bienvenue sur YOUPENDI IMMO SELECT', 'Votre espace est prêt. Toutes les opérations sont tracées et sécurisées.', 'app', 'info');
    }

    db()->insert('contact_messages', [
        'name' => 'Visiteur web', 'email' => 'hello@example.com', 'phone' => '+243990000000',
        'subject' => 'Recherche villa Goma', 'body' => 'Bonjour, je cherche une villa 4 chambres à Keshero pour septembre.',
        'created_at' => $t,
    ]);
}
