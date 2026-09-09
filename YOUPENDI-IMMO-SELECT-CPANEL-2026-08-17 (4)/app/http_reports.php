<?php

/**
 * Portfolio reporting for the administration area.
 *
 * This module deliberately reports on the property portfolio only. It does
 * not attempt to reconcile rent payments, which remain in the finance area.
 */
function app_portfolio_reports(): void
{
    $u = require_auth();
    if (!in_array($u['role'] ?? '', ['admin', 'finance', 'manager', 'supervisor', 'agent', 'owner'], true)) {
        abort(403, 'Les rapports du portefeuille sont réservés aux équipes autorisées.');
    }

    $data = report_dataset();

    if (str_input('export') === 'csv') {
        report_csv($data['report'], $data['rows']);
    }

    view('app/reports', [
        'title' => 'Rapports du portefeuille',
        'report' => $data['report'],
        'filters' => $data['filters'],
        'kpis' => $data['kpis'],
        'rows' => $data['rows'],
        'history' => $data['history'],
        'options' => $data['options'],
        'summary' => $data['summary'],
        'tailwind' => true,
    ], 'app');
}

function app_portfolio_report_pdf(): void
{
    $u = require_auth();
    if (!in_array($u['role'] ?? '', ['admin', 'finance', 'manager', 'supervisor', 'agent', 'owner'], true)) {
        abort(403, 'Les rapports du portefeuille sont réservés aux équipes autorisées.');
    }

    $data = report_dataset();
    $payload = report_table_payload($data['report'], $data['rows']);
    report_pdf(
        $data['report'],
        $data['reportTitle'],
        $data['reportReference'],
        $payload['headers'],
        $payload['data'],
        $data['summary'],
        str_input('download') === '1'
    );
}

function report_dataset(): array
{
    $report = str_input('report', 'dashboard');
    $allowedReports = ['dashboard', 'portfolio', 'occupation', 'vacant', 'owners', 'agents', 'tenants', 'contracts', 'movements'];
    if (!in_array($report, $allowedReports, true)) {
        $report = 'dashboard';
    }

    $filters = report_filters();
    $properties = report_properties($filters);
    $contracts = report_contracts($filters);
    $kpis = report_kpis($properties, $contracts, $filters);

    $rows = [
        'portfolio' => report_portfolio_rows($properties),
        'vacant' => report_vacant_rows($properties),
        'owners' => report_owner_rows($properties, $contracts),
        'agents' => report_agent_rows($properties, $contracts),
        'tenants' => report_tenant_rows($contracts),
        'contracts' => report_contract_rows($contracts),
        'movements' => report_movement_rows($properties, $contracts),
    ];

    $history = report_occupancy_history($contracts, $filters);
    $rows['occupation'] = $history;
    $options = report_filter_options($properties);
    $labels = report_labels();
    $codes = report_codes();
    $reportTitle = $labels[$report] ?? 'Rapports du portefeuille';
    $reportCode = $codes[$report] ?? 'R';
    $reportReference = 'RPT-' . $reportCode . '-' . date('Ymd') . '-U' . (int) (user()['id'] ?? 0);

    return [
        'report' => $report,
        'reportTitle' => $reportTitle,
        'reportCode' => $reportCode,
        'reportReference' => $reportReference,
        'reportViewer' => (user()['role'] ?? '') === 'owner' ? 'Rapport du bailleur' : 'Rapport administratif',
        'reportDescriptions' => report_descriptions(),
        'filters' => $filters,
        'properties' => $properties,
        'contracts' => $contracts,
        'kpis' => $kpis,
        'rows' => $rows,
        'history' => $history,
        'options' => $options,
        'summary' => report_summary($report, $properties, $contracts, $kpis, $rows),
    ];
}

function report_labels(): array
{
    return [
        'dashboard' => 'Vue d’ensemble',
        'portfolio' => 'Portefeuille immobilier',
        'occupation' => 'Occupation',
        'vacant' => 'Biens vacants',
        'owners' => 'Par propriétaire',
        'agents' => 'Par agent immobilier',
        'tenants' => 'Locataires',
        'contracts' => 'Contrats',
        'movements' => 'Mouvements',
    ];
}

function report_codes(): array
{
    return [
        'dashboard' => 'DASH',
        'portfolio' => 'A',
        'occupation' => 'B',
        'vacant' => 'C',
        'owners' => 'D',
        'agents' => 'E',
        'tenants' => 'F',
        'contracts' => 'G',
        'movements' => 'H',
    ];
}

function report_descriptions(): array
{
    return [
        'dashboard' => 'Indicateurs clés et priorités du portefeuille',
        'portfolio' => 'Biens, localisation, propriétaires et responsables',
        'occupation' => 'Occupation, vacance et évolution dans le temps',
        'vacant' => 'Biens vacants à relancer commercialement',
        'owners' => 'Synthèse des biens confiés par propriétaire',
        'agents' => 'Portefeuille et performance par agent',
        'tenants' => 'Locataires et périodes de leurs contrats',
        'contracts' => 'État des contrats et alertes d’échéance',
        'movements' => 'Entrées, sorties et changements du portefeuille',
    ];
}

function report_summary(string $report, array $properties, array $contracts, array $kpis, array $rows): array
{
    $propertyIds = array_values(array_filter(array_map('intval', array_column($properties, 'id'))));
    $contractIds = array_values(array_filter(array_map('intval', array_column($contracts, 'id'))));
    $scopeParts = [];
    $scopeParams = [];
    if ($propertyIds) {
        $in = implode(',', array_fill(0, count($propertyIds), '?'));
        $scopeParts[] = "property_id IN ($in)";
        array_push($scopeParams, ...$propertyIds);
    }
    if ($contractIds) {
        $in = implode(',', array_fill(0, count($contractIds), '?'));
        $scopeParts[] = "contract_id IN ($in)";
        array_push($scopeParams, ...$contractIds);
    }
    $scope = $scopeParts ? '(' . implode(' OR ', $scopeParts) . ')' : '1 = 0';
    $commissionTotal = (float) qtry_val("SELECT COALESCE(SUM(amount), 0) FROM commissions WHERE $scope", $scopeParams);
    $commissionValidated = (float) qtry_val("SELECT COALESCE(SUM(amount), 0) FROM commissions WHERE status IN ('validee', 'payee') AND $scope", $scopeParams);
    $commissionPaid = (float) qtry_val("SELECT COALESCE(SUM(amount), 0) FROM commissions WHERE status = 'payee' AND $scope", $scopeParams);
    $expenseTotal = $propertyIds
        ? (float) qtry_val(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE property_id IN (' . implode(',', array_fill(0, count($propertyIds), '?')) . ')',
            $propertyIds
        )
        : 0.0;

    $rowCount = $report === 'dashboard'
        ? count($properties)
        : count($rows[$report] ?? []);

    return [
        'rows' => $rowCount,
        'properties' => count($properties),
        'contracts' => count(array_filter($contracts, static fn(array $c): bool => in_array($c['status'] ?? '', ['actif', 'renouvele'], true))),
        'occupied' => (int) ($kpis[2]['value'] ?? 0),
        'available' => (int) ($kpis[1]['value'] ?? 0),
        'vacant' => (int) ($kpis[3]['value'] ?? 0),
        'owners' => (int) ($kpis[6]['value'] ?? 0),
        'agents' => (int) ($kpis[8]['value'] ?? 0),
        'commission_total' => $commissionTotal,
        'commission_validated' => $commissionValidated,
        'commission_paid' => $commissionPaid,
        'expenses' => $expenseTotal,
    ];
}

function report_table_payload(string $report, array $rows): array
{
    $report = $report === 'dashboard' ? 'portfolio' : $report;
    $headers = [];
    $data = [];
    switch ($report) {
        case 'occupation':
            $headers = ['Mois', 'Biens occupés'];
            foreach ($rows['occupation'] as $r) $data[] = [$r['label'], $r['value']];
            break;
        case 'vacant':
            $headers = ['Référence', 'Localisation', 'Propriétaire', 'Agent responsable', 'Date de libération', 'Durée de vacance (jours)', 'Statut'];
            foreach ($rows['vacant'] as $r) $data[] = [$r['reference'], $r['location'], $r['owner'], $r['agent'], dfr($r['released_at']), $r['vacancy_days'], $r['status']];
            break;
        case 'owners':
            $headers = ['Propriétaire', 'Biens confiés', 'Biens occupés', 'Biens vacants', 'Biens disponibles', 'Agents affectés', 'Contrats en cours'];
            foreach ($rows['owners'] as $r) $data[] = [$r['owner'], $r['total'], $r['occupied'], $r['vacant'], $r['available'], $r['agents'], $r['contracts']];
            break;
        case 'agents':
            $headers = ['Agent', 'Biens affectés', 'Disponibles', 'Occupés', 'Vacants', 'Visites réalisées', 'Locataires trouvés', 'Contrats conclus', 'Taux d’occupation'];
            foreach ($rows['agents'] as $r) $data[] = [$r['agent'], $r['assigned'], $r['available'], $r['occupied'], $r['vacant'], $r['visits'], $r['tenants'], $r['contracts'], $r['rate']];
            break;
        case 'tenants':
            $headers = ['Locataire', 'Bien occupé', 'Propriétaire', 'Agent responsable', 'Date d’entrée', 'Début du contrat', 'Fin du contrat', 'Statut'];
            foreach ($rows['tenants'] as $r) $data[] = [$r['tenant'], $r['property'], $r['owner'], $r['agent'], dfr($r['entry']), dfr($r['start']), dfr($r['end']), $r['status']];
            break;
        case 'contracts':
            $headers = ['Référence', 'Bien', 'Locataire', 'Propriétaire', 'Agent', 'Période', 'Statut', 'Alerte échéance'];
            foreach ($rows['contracts'] as $r) $data[] = [$r['reference'], $r['property'], $r['tenant'], $r['owner'], $r['agent'], $r['period'], $r['status'], $r['alert']];
            break;
        case 'movements':
            $headers = ['Date', 'Mouvement', 'Élément', 'Localisation'];
            foreach ($rows['movements'] as $r) $data[] = [dfr($r['date']), $r['type'], $r['subject'], $r['location']];
            break;
        case 'portfolio':
        default:
            $headers = ['Référence', 'Nom du bien', 'Catégorie', 'Type', 'Province', 'Ville', 'Commune', 'Quartier', 'Propriétaire', 'Agent responsable', 'Statut', 'Date d’enregistrement'];
            foreach ($rows['portfolio'] as $r) $data[] = [$r['reference'], $r['title'], $r['category'], $r['type'], $r['province'], $r['city'], $r['commune'], $r['quartier'], $r['owner'], $r['agent'], $r['status'], dfr($r['created_at'])];
            break;
    }
    return ['headers' => $headers, 'data' => $data];
}

function report_pdf(
    string $report,
    string $title,
    string $reference,
    array $headers,
    array $data,
    array $summary,
    bool $download = false
): never {
    $pageWidth = 842.0;
    $pageHeight = 595.0;
    $left = 32.0;
    $contentWidth = $pageWidth - 64.0;
    $weights = [];
    foreach ($headers as $index => $header) {
        $max = strlen((string) $header);
        foreach (array_slice($data, 0, 40) as $row) {
            $max = max($max, strlen((string) ($row[$index] ?? '')));
        }
        $weights[] = min(25, max(8, $max));
    }
    $weightTotal = max(1, array_sum($weights));
    $widths = array_map(static fn(float $weight): float => $contentWidth * $weight / $weightTotal, $weights);

    $pages = [];
    $offset = 0;
    $first = true;
    do {
        $capacity = $first ? 16 : 23;
        $chunk = array_slice($data, $offset, $capacity);
        $pages[] = ['rows' => $chunk, 'first' => $first];
        $offset += count($chunk);
        $first = false;
    } while ($offset < count($data) || count($pages) === 0);

    $pageCount = count($pages);
    $fontRef = 3 + $pageCount;
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn(int $i): string => (string) (3 + $i) . ' 0 R', range(0, $pageCount - 1))) . '] /Count ' . $pageCount . ' >>',
    ];
    $contentRefs = [];

    foreach ($pages as $pageIndex => $page) {
        $pageRef = 3 + $pageIndex;
        $contentRef = 3 + $pageCount + 1 + $pageIndex;
        $contentRefs[] = $contentRef;
        $objects[$pageRef] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight . '] /Resources << /Font << /F1 ' . $fontRef . ' 0 R >> >> /Contents ' . $contentRef . ' 0 R >>';
        $commands = [];
        $commands[] = 'q 0.02 0.58 0.78 rg ' . $left . ' 544 778 32 re f Q';
        $commands[] = report_pdf_text($left + 10, 556, 'YOUPENDI IMMO SELECT', 14, [1, 1, 1]);
        $commands[] = report_pdf_text($left + 10, 529, 'RAPPORT ' . strtoupper($title), 13, [0.04, 0.10, 0.16]);
        $commands[] = report_pdf_text($left + 10, 513, 'Référence : ' . $reference . '  |  Édité le : ' . date('d/m/Y à H:i'), 8, [0.25, 0.32, 0.40]);
        $commands[] = '0.02 0.58 0.78 RG 1 w ' . $left . ' 505 m ' . ($pageWidth - $left) . ' 505 l S';

        $tableTop = 478.0;
        if ($page['first']) {
            $summaryItems = [
                ['Biens', (string) $summary['properties']],
                ['Contrats actifs', (string) $summary['contracts']],
                ['Occupés', (string) $summary['occupied']],
                ['Vacants', (string) $summary['vacant']],
                ['Disponibles', (string) $summary['available']],
                ['Commissions', money($summary['commission_total'])],
                ['Commissions validées', money($summary['commission_validated'])],
                ['Dépenses', money($summary['expenses'])],
            ];
            $cellWidth = $contentWidth / 4;
            foreach ($summaryItems as $i => [$label, $value]) {
                $row = intdiv($i, 4);
                $col = $i % 4;
                $x = $left + $col * $cellWidth;
                $y = 468 - $row * 31;
                $commands[] = 'q 0.96 0.98 1 rg ' . $x . ' ' . ($y - 24) . ' ' . ($cellWidth - 5) . ' 26 re f Q';
                $commands[] = report_pdf_text($x + 7, $y - 9, strtoupper($label), 6.5, [0.32, 0.40, 0.48]);
                $commands[] = report_pdf_text($x + 7, $y - 21, $value, 9, [0.04, 0.10, 0.16]);
            }
            $tableTop = 400.0;
        }

        $headerHeight = 22.0;
        $rowHeight = 18.0;
        $x = $left;
        foreach ($headers as $i => $header) {
            $commands[] = 'q 0.02 0.58 0.78 rg ' . $x . ' ' . ($tableTop - $headerHeight) . ' ' . $widths[$i] . ' ' . $headerHeight . ' re f Q';
            $commands[] = report_pdf_text($x + 4, $tableTop - 15, report_pdf_clip($header, max(7, (int) floor($widths[$i] / 4.2))), 6.5, [1, 1, 1]);
            $x += $widths[$i];
        }
        $y = $tableTop - $headerHeight;
        if (!$page['rows']) {
            $commands[] = report_pdf_text($left + 7, $y - 13, 'Aucune donnée pour les filtres sélectionnés.', 8, [0.35, 0.40, 0.45]);
        }
        foreach ($page['rows'] as $rowIndex => $row) {
            $y -= $rowHeight;
            if ($rowIndex % 2 === 0) {
                $commands[] = 'q 0.97 0.99 1 rg ' . $left . ' ' . $y . ' ' . $contentWidth . ' ' . $rowHeight . ' re f Q';
            }
            $x = $left;
            foreach ($headers as $i => $_header) {
                $clip = max(7, (int) floor($widths[$i] / 4.2));
                $commands[] = report_pdf_text($x + 4, $y + 6, report_pdf_clip((string) ($row[$i] ?? '—'), $clip), 6.5, [0.13, 0.18, 0.24]);
                $x += $widths[$i];
            }
            $commands[] = '0.82 0.87 0.91 RG 0.4 w ' . $left . ' ' . $y . ' m ' . ($left + $contentWidth) . ' ' . $y . ' l S';
        }

        $commands[] = '0.02 0.58 0.78 RG 1 w ' . $left . ' 31 m ' . ($pageWidth - $left) . ' 31 l S';
        $commands[] = report_pdf_text($left, 18, 'YOUPENDI IMMO SELECT · L’immobilier de confiance', 7, [0.30, 0.38, 0.46]);
        $commands[] = report_pdf_text(650, 18, $reference . ' · Page ' . ($pageIndex + 1) . '/' . $pageCount, 7, [0.30, 0.38, 0.46]);
        $objects[$contentRef] = implode("\n", $commands);
    }

    $objects[$fontRef] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    ksort($objects);
    foreach ($objects as $number => $object) {
        $offsets[$number] = strlen($pdf);
        if ($number > $fontRef) {
            $pdf .= $number . " 0 obj\n<< /Length " . strlen($object) . " >>\nstream\n" . $object . "\nendstream\nendobj\n";
        } elseif ($number > 2 && in_array($number, $contentRefs, true)) {
            $pdf .= $number . " 0 obj\n<< /Length " . strlen($object) . " >>\nstream\n" . $object . "\nendstream\nendobj\n";
        } else {
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }
    }
    $xref = strlen($pdf);
    $maxObject = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxObject + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObject; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

    header('Content-Type: application/pdf');
    $disposition = $download ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="rapport-' . preg_replace('/[^a-z0-9-]+/i', '-', strtolower($report)) . '-' . date('Ymd') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function report_pdf_plain($value): string
{
    $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);
    $converted = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) : $value;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $converted);
}

function report_pdf_clip(string $value, int $max): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    if (function_exists('mb_strlen') && mb_strlen($value) > $max) {
        return mb_substr($value, 0, max(1, $max - 1)) . '…';
    }
    if (strlen($value) > $max) {
        return substr($value, 0, max(1, $max - 1)) . '...';
    }
    return $value;
}

function report_pdf_text(float $x, float $y, string $text, float $size, array $rgb): string
{
    return sprintf(
        "BT /F1 %.2f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET",
        $size,
        $rgb[0],
        $rgb[1],
        $rgb[2],
        $x,
        $y,
        report_pdf_plain($text)
    );
}

function report_csv(string $report, array $rows): never
{
    $payload = report_table_payload($report, $rows);
    csv_download('rapport-' . $report . '.csv', $payload['headers'], $payload['data']);
}

function report_filters(): array
{
    return [
        'from' => str_input('from'),
        'to' => str_input('to'),
        'province' => str_input('province'),
        'ville' => str_input('ville'),
        'commune' => str_input('commune'),
        'quartier' => str_input('quartier'),
        'owner_id' => int_input('owner_id'),
        'agent_id' => int_input('agent_id'),
        'category' => str_input('category'),
        'type' => str_input('type'),
        'status' => str_input('status'),
        'contract_status' => str_input('contract_status'),
    ];
}

function report_property_where(array $filters, string $alias = 'p'): array
{
    [$scope, $scopeParams] = property_scope_sql($alias);
    $where = [$scope];
    $params = $scopeParams;

    $equals = [
        'province' => "$alias.province",
        'ville' => "$alias.city",
        'commune' => "$alias.commune",
        'quartier' => "$alias.quartier",
        'type' => "$alias.type",
        'status' => "$alias.status",
        'owner_id' => "$alias.owner_id",
    ];
    foreach ($equals as $key => $column) {
        if ($filters[$key] !== '' && $filters[$key] !== 0) {
            $where[] = "$column = ?";
            $params[] = $filters[$key];
        }
    }
    if ($filters['agent_id']) {
        $where[] = "($alias.agent_apporteur_id = ? OR $alias.agent_commercial_id = ? OR $alias.manager_id = ?)";
        $params[] = $filters['agent_id'];
        $params[] = $filters['agent_id'];
        $params[] = $filters['agent_id'];
    }
    if ($filters['category'] !== '') {
        $where[] = "$alias.usage_type = ?";
        $params[] = $filters['category'];
    }
    if ($filters['from'] !== '') {
        $where[] = "$alias.created_at >= ?";
        $params[] = $filters['from'] . ' 00:00:00';
    }
    if ($filters['to'] !== '') {
        $where[] = "$alias.created_at <= ?";
        $params[] = $filters['to'] . ' 23:59:59';
    }
    return [implode(' AND ', $where), $params];
}

function report_contract_where(array $filters): array
{
    [$propertyWhere, $params] = report_property_where($filters, 'p');
    $where = [$propertyWhere];
    if ($filters['contract_status'] !== '') {
        $where[] = 'c.status = ?';
        $params[] = $filters['contract_status'];
    }
    if ($filters['from'] !== '') {
        $where[] = 'c.end_date >= ?';
        $params[] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $where[] = 'c.start_date <= ?';
        $params[] = $filters['to'];
    }
    return [implode(' AND ', $where), $params];
}

function report_properties(array $filters): array
{
    [$where, $params] = report_property_where($filters);
    return qtry_all(
        "SELECT p.*, p.agent_commercial_id AS agent_id,
                o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                u.first_name AS agent_first_name, u.last_name AS agent_last_name
         FROM properties p
         LEFT JOIN owners o ON o.id = p.owner_id
         LEFT JOIN immo_agents ia ON ia.id = p.agent_commercial_id
         LEFT JOIN users u ON u.id = ia.user_id
         WHERE $where
         ORDER BY p.created_at DESC, p.id DESC",
        $params
    );
}

function report_contracts(array $filters): array
{
    [$where, $params] = report_contract_where($filters);
    return qtry_all(
        "SELECT c.*, p.reference, p.title AS property_title, p.province, p.city, p.commune, p.quartier,
                o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                t.first_name AS tenant_first_name, t.last_name AS tenant_last_name,
                t.entry_date AS tenant_entry_date,
                u.first_name AS agent_first_name, u.last_name AS agent_last_name
         FROM contracts c
         INNER JOIN properties p ON p.id = c.property_id
         LEFT JOIN owners o ON o.id = c.owner_id
         LEFT JOIN tenants t ON t.id = c.tenant_id
         LEFT JOIN immo_agents ia ON ia.id = c.agent_id
         LEFT JOIN users u ON u.id = ia.user_id
         WHERE $where
         ORDER BY c.end_date ASC, c.id DESC",
        $params
    );
}

function report_kpis(array $properties, array $contracts, array $filters): array
{
    $activeProperties = array_values(array_filter($properties, static function (array $p): bool {
        return !in_array((string) ($p['status'] ?? ''), ['brouillon', 'en_attente', 'archive'], true);
    }));
    $occupied = count(array_filter($activeProperties, static fn(array $p): bool => ($p['status'] ?? '') === 'occupe'));
    $availableStatuses = ['disponible', 'publie', 'valide', 'visite_en_cours', 'reserve'];
    $available = count(array_filter($activeProperties, static fn(array $p): bool => in_array($p['status'] ?? '', $availableStatuses, true)));
    $vacant = max(0, count($activeProperties) - $occupied);
    $activeContracts = array_filter($contracts, static fn(array $c): bool => in_array($c['status'] ?? '', ['actif', 'renouvele'], true));
    $renewals = array_filter($activeContracts, static function (array $c): bool {
        if (empty($c['end_date'])) {
            return false;
        }
        $days = (strtotime($c['end_date']) - strtotime(today())) / 86400;
        return $days >= 0 && $days <= 90;
    });
    $newCount = 0;
    foreach ($properties as $p) {
        if ((!$filters['from'] || substr((string) ($p['created_at'] ?? ''), 0, 10) >= $filters['from'])
            && (!$filters['to'] || substr((string) ($p['created_at'] ?? ''), 0, 10) <= $filters['to'])) {
            $newCount++;
        }
    }
    $distinct = static function (array $items, string $key): int {
        $values = [];
        foreach ($items as $item) {
            if (($item[$key] ?? '') !== '' && ($item[$key] ?? null) !== null) {
                $values[(string) $item[$key]] = true;
            }
        }
        return count($values);
    };
    $occupiedBase = max(1, count($activeProperties));
    return [
        ['label' => 'Total des biens', 'value' => count($properties), 'hint' => 'Biens enregistrés'],
        ['label' => 'Biens disponibles', 'value' => $available, 'hint' => 'Prêts à être loués'],
        ['label' => 'Biens occupés', 'value' => $occupied, 'hint' => 'Avec locataire actif'],
        ['label' => 'Biens vacants', 'value' => $vacant, 'hint' => 'Sans locataire actif'],
        ['label' => 'Taux d’occupation', 'value' => number_format($occupied * 100 / $occupiedBase, 1, ',', ' ') . ' %', 'hint' => 'Sur le portefeuille actif'],
        ['label' => 'Nouveaux biens', 'value' => $newCount, 'hint' => 'Dans la période sélectionnée'],
        ['label' => 'Propriétaires actifs', 'value' => $distinct($activeProperties, 'owner_id'), 'hint' => 'Avec au moins un bien'],
        ['label' => 'Locataires actifs', 'value' => $distinct($activeContracts, 'tenant_id'), 'hint' => 'Avec contrat actif'],
        ['label' => 'Agents actifs', 'value' => $distinct($activeProperties, 'agent_id'), 'hint' => 'Avec biens affectés'],
        ['label' => 'Contrats actifs', 'value' => count($activeContracts), 'hint' => 'En cours'],
        ['label' => 'À renouveler', 'value' => count($renewals), 'hint' => 'Échéance sous 90 jours'],
        ['label' => 'Taux de vacance', 'value' => number_format($vacant * 100 / $occupiedBase, 1, ',', ' ') . ' %', 'hint' => 'Biens sans locataire actif'],
    ];
}

function report_portfolio_rows(array $properties): array
{
    return array_map(static function (array $p): array {
        return [
            'reference' => $p['reference'] ?? '—',
            'title' => $p['title'] ?? '—',
            'category' => $p['usage_type'] ?? '—',
            'type' => property_types()[$p['type'] ?? ''] ?? ($p['type'] ?? '—'),
            'location' => trim(($p['province'] ?? '') . ' · ' . ($p['city'] ?? '—') . ' · ' . ($p['commune'] ?? '') . ' · ' . ($p['quartier'] ?? ''), ' ·'),
            'province' => $p['province'] ?? province_for_city($p['city'] ?? ''),
            'city' => $p['city'] ?? '—',
            'commune' => $p['commune'] ?? '—',
            'quartier' => $p['quartier'] ?? '—',
            'owner' => trim(($p['owner_first_name'] ?? '') . ' ' . ($p['owner_last_name'] ?? '')) ?: '—',
            'agent' => trim(($p['agent_first_name'] ?? '') . ' ' . ($p['agent_last_name'] ?? '')) ?: '—',
            'status' => $p['status'] ?? '—',
            'created_at' => $p['created_at'] ?? '',
        ];
    }, $properties);
}

function report_vacant_rows(array $properties): array
{
    $rows = [];
    foreach ($properties as $p) {
        if (in_array($p['status'] ?? '', ['brouillon', 'en_attente', 'archive', 'occupe'], true)) {
            continue;
        }
        $since = $p['available_from'] ?? $p['updated_at'] ?? $p['created_at'] ?? '';
        $days = $since ? max(0, (int) floor((strtotime(today()) - strtotime($since)) / 86400)) : 0;
        $rows[] = [
            'reference' => $p['reference'] ?? '—',
            'location' => trim(($p['province'] ?? '') . ' · ' . ($p['city'] ?? '—') . ' · ' . ($p['commune'] ?? '') . ' · ' . ($p['quartier'] ?? ''), ' ·'),
            'owner' => trim(($p['owner_first_name'] ?? '') . ' ' . ($p['owner_last_name'] ?? '')) ?: '—',
            'agent' => trim(($p['agent_first_name'] ?? '') . ' ' . ($p['agent_last_name'] ?? '')) ?: '—',
            'released_at' => $since,
            'vacancy_days' => $days,
            'status' => $p['status'] ?? '—',
        ];
    }
    usort($rows, static fn(array $a, array $b): int => $b['vacancy_days'] <=> $a['vacancy_days']);
    return $rows;
}

function report_owner_rows(array $properties, array $contracts): array
{
    $grouped = [];
    foreach ($properties as $p) {
        $key = (string) ($p['owner_id'] ?? 'none');
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'owner' => trim(($p['owner_first_name'] ?? '') . ' ' . ($p['owner_last_name'] ?? '')) ?: 'Sans propriétaire',
                'total' => 0, 'occupied' => 0, 'vacant' => 0, 'available' => 0, 'agents' => [], 'contracts' => 0,
            ];
        }
        $grouped[$key]['total']++;
        if (($p['status'] ?? '') === 'occupe') {
            $grouped[$key]['occupied']++;
        } elseif (in_array($p['status'] ?? '', ['disponible', 'publie', 'valide', 'visite_en_cours', 'reserve'], true)) {
            $grouped[$key]['available']++;
        } else {
            $grouped[$key]['vacant']++;
        }
        $agent = trim(($p['agent_first_name'] ?? '') . ' ' . ($p['agent_last_name'] ?? ''));
        if ($agent) {
            $grouped[$key]['agents'][$agent] = true;
        }
    }
    foreach ($contracts as $c) {
        $key = (string) ($c['owner_id'] ?? 'none');
        if (isset($grouped[$key]) && in_array($c['status'] ?? '', ['actif', 'renouvele'], true)) {
            $grouped[$key]['contracts']++;
        }
    }
    foreach ($grouped as &$row) {
        $row['agents'] = implode(', ', array_keys($row['agents']));
    }
    unset($row);
    return array_values($grouped);
}

function report_agent_rows(array $properties, array $contracts): array
{
    $grouped = [];
    foreach ($properties as $p) {
        $agentId = (string) ($p['agent_commercial_id'] ?? 'none');
        $agent = trim(($p['agent_first_name'] ?? '') . ' ' . ($p['agent_last_name'] ?? '')) ?: 'Non affecté';
        if (!isset($grouped[$agentId])) {
            $grouped[$agentId] = ['agent' => $agent, 'assigned' => 0, 'available' => 0, 'occupied' => 0, 'vacant' => 0, 'visits' => 0, 'tenants' => 0, 'contracts' => 0, 'rate' => '0 %'];
        }
        $grouped[$agentId]['assigned']++;
        if (($p['status'] ?? '') === 'occupe') {
            $grouped[$agentId]['occupied']++;
        } elseif (in_array($p['status'] ?? '', ['disponible', 'publie', 'valide', 'visite_en_cours', 'reserve'], true)) {
            $grouped[$agentId]['available']++;
        } else {
            $grouped[$agentId]['vacant']++;
        }
    }
    foreach ($contracts as $c) {
        $agentId = (string) ($c['agent_id'] ?? 'none');
        if (!isset($grouped[$agentId])) {
            continue;
        }
        if (in_array($c['status'] ?? '', ['actif', 'renouvele'], true)) {
            $grouped[$agentId]['contracts']++;
            $grouped[$agentId]['tenants']++;
        }
    }
    foreach (qtry_all("SELECT agent_id, COUNT(*) AS visits FROM visits WHERE status = 'realisee' GROUP BY agent_id") as $visit) {
        $agentId = (string) ($visit['agent_id'] ?? 'none');
        if (isset($grouped[$agentId])) {
            $grouped[$agentId]['visits'] = (int) $visit['visits'];
        }
    }
    foreach ($grouped as &$row) {
        $row['rate'] = number_format($row['occupied'] * 100 / max(1, $row['assigned']), 1, ',', ' ') . ' %';
    }
    unset($row);
    return array_values($grouped);
}

function report_tenant_rows(array $contracts): array
{
    $rows = [];
    foreach ($contracts as $c) {
        $rows[] = [
            'tenant' => trim(($c['tenant_first_name'] ?? '') . ' ' . ($c['tenant_last_name'] ?? '')) ?: '—',
            'property' => $c['property_title'] ?? '—',
            'owner' => trim(($c['owner_first_name'] ?? '') . ' ' . ($c['owner_last_name'] ?? '')) ?: '—',
            'agent' => trim(($c['agent_first_name'] ?? '') . ' ' . ($c['agent_last_name'] ?? '')) ?: '—',
            'entry' => $c['tenant_entry_date'] ?? $c['start_date'] ?? '',
            'start' => $c['start_date'] ?? '',
            'end' => $c['end_date'] ?? '',
            'status' => $c['status'] ?? '—',
        ];
    }
    return $rows;
}

function report_contract_rows(array $contracts): array
{
    $rows = [];
    foreach ($contracts as $c) {
        $days = !empty($c['end_date']) ? (strtotime($c['end_date']) - strtotime(today())) / 86400 : 9999;
        $renewal = in_array($c['status'] ?? '', ['actif', 'renouvele'], true) && $days >= 0 && $days <= 90;
        $alert = '—';
        if (in_array($c['status'] ?? '', ['actif', 'renouvele'], true) && $days >= 0) {
            if ($days <= 30) {
                $alert = 'Échéance sous 30 jours';
            } elseif ($days <= 60) {
                $alert = 'Échéance sous 60 jours';
            } elseif ($days <= 90) {
                $alert = 'Échéance sous 90 jours';
            }
        }
        $rows[] = [
            'reference' => $c['reference'] ?? '—',
            'property' => $c['property_title'] ?? '—',
            'tenant' => trim(($c['tenant_first_name'] ?? '') . ' ' . ($c['tenant_last_name'] ?? '')) ?: '—',
            'owner' => trim(($c['owner_first_name'] ?? '') . ' ' . ($c['owner_last_name'] ?? '')) ?: '—',
            'agent' => trim(($c['agent_first_name'] ?? '') . ' ' . ($c['agent_last_name'] ?? '')) ?: '—',
            'period' => trim(dfr($c['start_date'] ?? '') . ' → ' . dfr($c['end_date'] ?? '')),
            'status' => $c['status'] ?? '—',
            'renewal' => $renewal ? 'Oui' : 'Non',
            'alert' => $alert,
        ];
    }
    return $rows;
}

function report_movement_rows(array $properties, array $contracts): array
{
    $rows = [];
    $propertyIds = array_values(array_filter(array_map('intval', array_column($properties, 'id'))));
    foreach ($properties as $p) {
        $rows[] = ['date' => $p['created_at'] ?? '', 'type' => 'Nouveau bien', 'subject' => $p['reference'] . ' — ' . $p['title'], 'location' => trim(($p['province'] ?? '') . ' · ' . ($p['city'] ?? '—'), ' ·')];
    }
    foreach ($contracts as $c) {
        $rows[] = ['date' => $c['start_date'] ?? '', 'type' => 'Nouveau locataire', 'subject' => ($c['tenant_first_name'] ?? '') . ' ' . ($c['tenant_last_name'] ?? ''), 'location' => $c['property_title'] ?? '—'];
        if (!empty($c['end_date'])) {
            $status = $c['status'] ?? '';
            if ($status === 'renouvele') {
                $rows[] = ['date' => $c['created_at'] ?? $c['end_date'], 'type' => 'Contrat renouvelé', 'subject' => $c['reference'] ?? '—', 'location' => $c['property_title'] ?? '—'];
            } elseif ($status === 'resilie') {
                $rows[] = ['date' => $c['end_date'], 'type' => 'Contrat résilié', 'subject' => $c['reference'] ?? '—', 'location' => $c['property_title'] ?? '—'];
            } else {
                $rows[] = ['date' => $c['end_date'], 'type' => 'Départ de locataire', 'subject' => $c['reference'] ?? '—', 'location' => $c['property_title'] ?? '—'];
            }
        }
    }
    if ($propertyIds) {
        $in = implode(',', array_fill(0, count($propertyIds), '?'));
        foreach (qtry_all(
            "SELECT l.created_at, l.action, l.entity_id, l.old_value, l.new_value,
                    p.reference, p.title, p.province, p.city
             FROM activity_logs l
             LEFT JOIN properties p ON p.id = l.entity_id
             WHERE l.entity = 'properties' AND l.entity_id IN ($in)
             ORDER BY l.created_at DESC",
            $propertyIds
        ) as $log) {
            $old = json_decode((string) ($log['old_value'] ?? ''), true) ?: [];
            $new = json_decode((string) ($log['new_value'] ?? ''), true) ?: [];
            $oldStatus = (string) ($old['status'] ?? '');
            $newStatus = (string) ($new['status'] ?? '');
            $type = $newStatus === 'occupe' && $oldStatus !== 'occupe'
                ? 'Bien nouvellement occupé'
                : (($oldStatus === 'occupe' && $newStatus !== 'occupe') ? 'Bien devenu vacant' : '');
            if ($type !== '') {
                $rows[] = [
                    'date' => $log['created_at'] ?? '',
                    'type' => $type,
                    'subject' => trim(($log['reference'] ?? '—') . ' — ' . ($log['title'] ?? '')),
                    'location' => trim(($log['province'] ?? '') . ' · ' . ($log['city'] ?? '—'), ' ·'),
                ];
            }
        }
    }
    usort($rows, static fn(array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));
    return $rows;
}

function report_occupancy_history(array $contracts, array $filters): array
{
    $end = $filters['to'] ? strtotime($filters['to']) : strtotime(today());
    $start = $filters['from'] ? strtotime($filters['from']) : strtotime('-5 months', $end);
    $months = [];
    $cursor = strtotime(date('Y-m-01', $start));
    $last = strtotime(date('Y-m-01', $end));
    while ($cursor <= $last) {
        $monthEnd = date('Y-m-t', $cursor);
        $monthStart = date('Y-m-01', $cursor);
        $occupied = [];
        foreach ($contracts as $c) {
            if (!in_array($c['status'] ?? '', ['actif', 'renouvele'], true)) {
                continue;
            }
            if (($c['start_date'] ?? '') <= $monthEnd && ($c['end_date'] ?? '9999-12-31') >= $monthStart) {
                $occupied[(string) ($c['property_id'] ?? '')] = true;
            }
        }
        $months[] = ['label' => date('M Y', $cursor), 'value' => count($occupied)];
        $cursor = strtotime('+1 month', $cursor);
    }
    return $months;
}

function report_filter_options(array $properties = []): array
{
    $restricted = in_array(user()['role'] ?? '', ['agent', 'supervisor', 'owner'], true);
    if ($restricted) {
        $ownerMap = [];
        $agentMap = [];
        foreach ($properties as $p) {
            if (!empty($p['owner_id'])) {
                $ownerMap[(string) $p['owner_id']] = [
                    'id' => $p['owner_id'],
                    'first_name' => $p['owner_first_name'] ?? '',
                    'last_name' => $p['owner_last_name'] ?? '',
                ];
            }
            if (!empty($p['agent_id'])) {
                $agentMap[(string) $p['agent_id']] = [
                    'id' => $p['agent_id'],
                    'first_name' => $p['agent_first_name'] ?? '',
                    'last_name' => $p['agent_last_name'] ?? '',
                ];
            }
        }
        $owners = array_values($ownerMap);
        $agents = array_values($agentMap);
    } else {
        $owners = qtry_all("SELECT id, first_name, last_name FROM owners WHERE deleted_at IS NULL AND COALESCE(status, 'actif') <> 'supprime' ORDER BY last_name, first_name");
        $agents = qtry_all(
            "SELECT ia.id, u.first_name, u.last_name
             FROM immo_agents ia LEFT JOIN users u ON u.id = ia.user_id
             ORDER BY u.last_name, u.first_name"
        );
    }
    $values = static function (string $column): array {
        return array_values(array_filter(array_unique(array_map('strval', array_column(qtry_all("SELECT DISTINCT $column FROM properties WHERE $column IS NOT NULL AND $column <> '' ORDER BY $column"), $column)))));
    };
    return [
        'provinces' => provinces_rdc(),
        'cities' => cities(),
        'communes' => $values('commune'),
        'quartiers' => $values('quartier'),
        'owners' => $owners,
        'agents' => $agents,
        'types' => property_types(),
        'statuses' => property_statuses(),
        'categories' => ['residentiel' => 'Résidentiel', 'commercial' => 'Commercial', 'terrain' => 'Terrain', 'autre' => 'Autre'],
        'contractStatuses' => ['actif' => 'Actif', 'renouvele' => 'Renouvelé', 'expire' => 'Expiré', 'resilie' => 'Résilié'],
    ];
}