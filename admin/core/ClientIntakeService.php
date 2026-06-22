<?php

declare(strict_types=1);

class ClientIntakeService
{
    /** @var list<array{keywords: list<string>, service: string, fee_min: float, fee_max: float}> */
    private const CATALOG = [
        [
            'keywords' => ['power of attorney', 'poa', 'attorney', 'proxy'],
            'service'  => 'Power of Attorney',
            'fee_min'  => 150,
            'fee_max'  => 450,
        ],
        [
            'keywords' => ['property', 'sale', 'purchase', 'conveyance', 'deed', 'land'],
            'service'  => 'Property / Conveyancing',
            'fee_min'  => 200,
            'fee_max'  => 800,
        ],
        [
            'keywords' => ['affidavit', 'sworn', 'statutory declaration', 'declaration'],
            'service'  => 'Affidavit / Statutory Declaration',
            'fee_min'  => 100,
            'fee_max'  => 300,
        ],
        [
            'keywords' => ['apostille', 'legalis', 'embassy', 'foreign', 'overseas'],
            'service'  => 'Apostille & Legalisation',
            'fee_min'  => 120,
            'fee_max'  => 500,
        ],
        [
            'keywords' => ['company', 'director', 'board resolution', 'corporate', 'certificate of incorporation'],
            'service'  => 'Corporate Notarisation',
            'fee_min'  => 175,
            'fee_max'  => 600,
        ],
        [
            'keywords' => ['certified copy', 'true copy', 'copy certification'],
            'service'  => 'Certified Copy',
            'fee_min'  => 75,
            'fee_max'  => 200,
        ],
    ];

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (Database::tableExists('client_intake_submissions')) {
            return;
        }

        $migration = __DIR__ . '/../sql/migrate_case_features.php';
        if (is_file($migration)) {
            try {
                require $migration;
            } catch (Throwable $e) {
                error_log('[ClientIntakeService] Schema migration failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @return array{service: string, fee_min: float, fee_max: float, checklist: list<array<string, mixed>>, notes: string}
     */
    public static function analyze(string $description): array
    {
        $lower = strtolower(trim($description));
        $best = null;
        $bestScore = 0;

        foreach (self::CATALOG as $entry) {
            $score = 0;
            foreach ($entry['keywords'] as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $score += mb_strlen($keyword);
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entry;
            }
        }

        if ($best === null) {
            $best = [
                'service' => 'General Notarisation',
                'fee_min' => 100,
                'fee_max' => 350,
            ];
        }

        $checklist = CaseChecklistService::previewChecklistForService($best['service']);
        $notes = 'Based on your description, we recommend a **' . $best['service'] . '** matter';

        $lower = strtolower(trim($description));
        if (preg_match('/\b(witness|witnesses)\b/', $lower)) {
            $notes .= '. Witness requirements may apply — we will confirm when you book';
        }
        if (preg_match('/\b(abroad|overseas|foreign|apostille|legali[sz]ation)\b/', $lower)) {
            $notes .= '. For documents used abroad, mention this at booking so we can advise on apostille or legalisation';
        }
        if (preg_match('/\b(urgent|rush|asap|today|tomorrow)\b/', $lower)) {
            $notes .= '. You mentioned urgency — ask about our soonest available appointment when you call or book online';
        }
        $notes .= '. Please bring valid photo ID and any original documents related to your request.';

        return [
            'service'   => $best['service'],
            'fee_min'   => (float) $best['fee_min'],
            'fee_max'   => (float) $best['fee_max'],
            'checklist' => $checklist,
            'notes'     => $notes,
        ];
    }

    public static function submit(int $clientId, string $description): int
    {
        self::ensureSchema();
        $description = trim($description);
        if ($description === '') {
            throw new RuntimeException('Please describe what you need.');
        }

        $analysis = self::analyze($description);
        $companyId = TenantService::isEnabled() ? TenantService::id() : null;

        $row = [
            'client_id'           => $clientId,
            'matter_description'  => $description,
            'suggested_service'   => $analysis['service'],
            'suggested_fee_min'   => $analysis['fee_min'],
            'suggested_fee_max'   => $analysis['fee_max'],
            'checklist_preview'   => json_encode($analysis['checklist'], JSON_UNESCAPED_UNICODE),
            'ai_notes'            => $analysis['notes'],
            'status'              => 'pending',
        ];

        if ($companyId !== null && Database::columnExists('client_intake_submissions', 'company_id')) {
            $row['company_id'] = $companyId;
        }

        $id = insertTableRow('client_intake_submissions', $row);

        $client = Database::fetch('SELECT first_name, last_name, email FROM clients WHERE id = ?', [$clientId]);
        $clientName = $client ? clientFullName($client) : 'Client';

        try {
            Database::query(
                'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                 SELECT u.id, ?, ?, ?, ?, 0, NOW()
                 FROM users u
                 WHERE u.role IN (\'admin\', \'super_admin\')' . (TenantService::isEnabled() ? ' AND u.company_id = ?' : '') . '
                 LIMIT 20',
                array_merge(
                    [
                        'intake',
                        'New client intake',
                        $clientName . ' submitted a matter request: ' . mb_substr($description, 0, 80),
                        url('pages/clients.php'),
                    ],
                    TenantService::isEnabled() ? [$companyId] : []
                )
            );
        } catch (Throwable $e) {
            // notifications optional
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public static function recentForAdmin(int $limit = 10): array
    {
        self::ensureSchema();
        if (!Database::tableExists('client_intake_submissions')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if (TenantService::isEnabled() && Database::columnExists('client_intake_submissions', 'company_id')) {
            $where[] = 's.company_id = ?';
            $params[] = TenantService::id();
        }
        $params[] = $limit;

        return Database::fetchAll(
            "SELECT s.*, c.first_name, c.last_name, c.email
             FROM client_intake_submissions s
             INNER JOIN clients c ON c.id = s.client_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY s.created_at DESC
             LIMIT ?",
            $params
        );
    }
}
