<?php

declare(strict_types=1);

/**
 * Professional notary client / engagement letters (HTML + optional PDF).
 */
class ClientLetterService
{
    public const SECTION_KEYS = [
        'introduction',
        'fees_and_charges',
        'service_information',
        'terms_and_conditions',
        'complaints_regulatory',
        'additional_notes',
        'signature',
    ];

    public static function sectionLabels(): array
    {
        return [
            'introduction'          => 'Introduction',
            'fees_and_charges'      => 'Fees and Charges (Price)',
            'service_information'   => 'Service Information',
            'terms_and_conditions'  => 'Terms and Conditions',
            'complaints_regulatory' => 'Complaints & Regulatory',
            'additional_notes'      => 'Additional Notes',
            'signature'             => 'Signature',
        ];
    }

    public static function placeholderHelp(): array
    {
        return [
            '{{client_name}}'         => 'Client or company name',
            '{{client_address}}'      => 'Client address',
            '{{case_number}}'         => 'Matter / case reference',
            '{{date}}'                => 'Letter date (DD/MM/YYYY)',
            '{{fee_amount}}'          => 'Total fee for this case',
            '{{service_description}}' => 'Primary service description',
            '{{company_name}}'        => 'Your company name',
            '{{company_website}}'     => 'Website link',
            '{{additional_notes}}'    => 'Notes from case or instructions',
        ];
    }

    public static function builtinDefaultSections(): array
    {
        return [
            'introduction' => <<<'HTML'
<p>As a Notary Public I hold an official seal and am required to keep full records of all notarial acts. Further information about my practice and privacy policy is available at {{company_website}}.</p>
<p>This letter confirms the basis on which I will act for you in connection with <strong>{{service_description}}</strong> (matter reference <strong>{{case_number}}</strong>).</p>
HTML,
            'fees_and_charges' => <<<'HTML'
<p><strong>Price:</strong></p>
<p>The fee for this transaction will be <strong>{{fee_amount}}</strong>. This fee includes standard disbursements (legalisation fees, postage, courier, etc.) unless stated otherwise below.</p>
{{services_list}}
<p>Some documents require an apostille from the UK Foreign, Commonwealth and Development Office. I will advise you if this applies and any additional cost.</p>
<p>My fees are not subject to VAT unless otherwise stated.</p>
<p>Payment may be made by cash, card, or bank transfer. If unforeseen complications arise, I reserve the right to revise the fee estimate after discussion with you.</p>
HTML,
            'service_information' => <<<'HTML'
<p><strong>Service Information</strong></p>
<p>Timescales and requirements vary depending on the documents and destination country. I will advise you once I have reviewed your papers.</p>
<ol>
<li>Receiving and reviewing your documents and confirming requirements.</li>
<li>Liaising with legal advisers, Companies House, or other bodies as required.</li>
<li>Checking identity, capacity, and authority of signatories.</li>
<li>Checking with issuing authorities for certification of documents as required.</li>
<li>Meeting with the signatory to verify identity and that they understand and execute the document freely.</li>
<li>Drafting and affixing or endorsing the notarial certificate.</li>
<li>Arranging legalisation of the document as appropriate.</li>
<li>Arranging storage of copies in accordance with the Notarial Practice Rules 2019.</li>
</ol>
HTML,
            'terms_and_conditions' => <<<'HTML'
<p>Our retainer is subject to the following terms:</p>
<ul>
<li>You will provide complete and accurate information and cooperate promptly with reasonable requests.</li>
<li>I will perform services with reasonable skill and care in accordance with professional standards applicable to notaries.</li>
<li>Documents must be produced in a form suitable for notarisation; I may decline to act if requirements cannot be met.</li>
<li>This letter, together with any agreed amendments in writing, constitutes the agreement between us.</li>
</ul>
HTML,
            'complaints_regulatory' => <<<'HTML'
<p><strong>Redress</strong></p>
<p>I am insured under a professional indemnity policy for at least £1,000,000.00.</p>
<p><strong>Complaints and Regulatory Information</strong></p>
<ol>
<li>I am regulated through the Faculty Office of the Archbishop of Canterbury.<br>
The Faculty Office, 1, The Sanctuary, Westminster, London SW1P 3JT</li>
<li>If you are dissatisfied with my service, please contact me in the first instance.</li>
<li>If we cannot resolve your complaint, you may use the Notaries Society approved Complaints Procedure.</li>
<li>Written complaints may be sent to: The Secretary of The Notaries Society, P O Box 7655, Milton Keynes MK11 9NR</li>
<li>If your complaint remains unresolved after eight weeks, you may refer it to the Legal Ombudsman.<br>
Legal Ombudsman, P O Box 6806, Wolverhampton WV1 9WJ — legalombudsman.org.uk</li>
<li>If you decide to make a complaint to the Legal Ombudsman, you must refer your matter to the Legal Ombudsman within six months from the conclusion of the complaint process.</li>
</ol>
HTML,
            'additional_notes' => '<p>{{additional_notes}}</p>',
            'signature' => <<<'HTML'
<p>Yours faithfully,</p>
<p>&nbsp;</p>
<p><strong>For and on behalf of {{company_name}}</strong></p>
HTML,
        ];
    }

    public static function getDefaultTemplateSections(): array
    {
        if (!Database::tableExists('client_letter_templates')) {
            return self::builtinDefaultSections();
        }

        $row = Database::fetch(
            'SELECT sections FROM client_letter_templates WHERE is_default = 1 ORDER BY id ASC LIMIT 1'
        );

        if (!$row || empty($row['sections'])) {
            return self::builtinDefaultSections();
        }

        $decoded = json_decode((string) $row['sections'], true);

        return is_array($decoded) ? self::normalizeSections($decoded) : self::builtinDefaultSections();
    }

    public static function saveNamedTemplate(string $name, array $sections, bool $asDefault = false): int
    {
        if (!Database::tableExists('client_letter_templates')) {
            throw new RuntimeException('Run: php admin/sql/migrate_client_letters.php');
        }

        $name     = trim($name) !== '' ? trim($name) : 'Template';
        $sections = self::normalizeSections($sections);
        $json     = json_encode($sections, JSON_UNESCAPED_UNICODE);

        if ($asDefault) {
            Database::query('UPDATE client_letter_templates SET is_default = 0');
        }

        $existing = Database::fetch(
            'SELECT id FROM client_letter_templates WHERE name = ? LIMIT 1',
            [$name]
        );

        if ($existing) {
            $id = (int) $existing['id'];
            Database::query(
                'UPDATE client_letter_templates SET sections = ?, is_default = ?, updated_at = NOW() WHERE id = ?',
                [$json, $asDefault ? 1 : 0, $id]
            );

            return $id;
        }

        Database::query(
            'INSERT INTO client_letter_templates (name, is_default, sections) VALUES (?, ?, ?)',
            [$name, $asDefault ? 1 : 0, $json]
        );

        return (int) Database::getInstance()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public static function listTemplates(): array
    {
        if (!Database::tableExists('client_letter_templates')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT id, name, is_default, updated_at FROM client_letter_templates ORDER BY is_default DESC, name ASC'
        );
    }

    public static function getTemplateSections(int $templateId): array
    {
        if ($templateId <= 0 || !Database::tableExists('client_letter_templates')) {
            return self::getDefaultTemplateSections();
        }

        $row = Database::fetch('SELECT sections FROM client_letter_templates WHERE id = ?', [$templateId]);
        if (!$row || empty($row['sections'])) {
            return self::getDefaultTemplateSections();
        }

        $decoded = json_decode((string) $row['sections'], true);

        return is_array($decoded) ? self::normalizeSections($decoded) : self::getDefaultTemplateSections();
    }

    public static function getSectionsForCase(int $caseId): array
    {
        $defaults = self::getDefaultTemplateSections();

        if (!Database::columnExists('cases', 'client_letter_sections')) {
            return $defaults;
        }

        $row = Database::fetch('SELECT client_letter_sections FROM cases WHERE id = ?', [$caseId]);
        if (!$row || empty($row['client_letter_sections'])) {
            return $defaults;
        }

        $decoded = json_decode((string) $row['client_letter_sections'], true);

        return is_array($decoded) ? array_merge($defaults, self::normalizeSections($decoded)) : $defaults;
    }

    public static function saveCaseSections(int $caseId, array $sections): void
    {
        if (!Database::columnExists('cases', 'client_letter_sections')) {
            throw new RuntimeException('Run: php admin/sql/migrate_client_letters.php');
        }

        $json = json_encode(self::normalizeSections($sections), JSON_UNESCAPED_UNICODE);
        Database::query(
            'UPDATE cases SET client_letter_sections = ?, updated_at = NOW() WHERE id = ?',
            [$json, $caseId]
        );
    }

    public static function sectionsFromPost(array $post): array
    {
        $sections = [];
        foreach (self::SECTION_KEYS as $key) {
            $field = 'letter_' . $key;
            if (array_key_exists($field, $post)) {
                $sections[$key] = trim((string) $post[$field]);
            }
        }

        return self::normalizeSections($sections);
    }

    public static function normalizeSections(array $sections): array
    {
        $defaults = self::builtinDefaultSections();
        $out      = [];

        foreach (self::SECTION_KEYS as $key) {
            $out[$key] = trim((string) ($sections[$key] ?? $defaults[$key] ?? ''));
        }

        return $out;
    }

    public static function buildContext(array $case, array $client, ?array $company = null): array
    {
        $company = $company ?? getCompanySettings();
        $services = CaseService::getCaseServices($case);
        $serviceLines = [];

        foreach ($services as $service) {
            $serviceLines[] = '<li>' . e($service['type']) . ' — ' . formatCurrency((float) $service['fee']) . '</li>';
        }

        if ($serviceLines === []) {
            $serviceLines[] = '<li>' . e($case['service_type'] ?? 'Notary services') . ' — '
                . formatCurrency((float) ($case['service_fee'] ?? 0)) . '</li>';
        }

        $website = trim((string) ($company['company_website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }
        $websiteHtml = $website !== ''
            ? '<a href="' . e($website) . '" class="cl-link">' . e(preg_replace('#^https?://#i', '', $website)) . '</a>'
            : e(companyBrandName($company));

        $recipient = trim((string) ($client['company_name'] ?? ''));
        if ($recipient === '') {
            $recipient = clientFullName($client) ?: 'Client';
        }

        $feeAmount = formatCurrency((float) ($case['service_fee'] ?? 0));
        $serviceDescription = trim((string) ($case['service_type'] ?? $case['title'] ?? 'Notary services'));
        $instructions = trim((string) ($case['client_instructions'] ?? ''));
        $additionalNotes = trim((string) ($case['description'] ?? ''));
        if ($additionalNotes === '' && $instructions !== '') {
            $additionalNotes = $instructions;
        }

        return [
            'company_name'          => companyBrandName($company),
            'company_address'       => nl2br(e(trim((string) ($company['address'] ?? '')))),
            'company_email'         => trim((string) ($company['office_email'] ?? '')),
            'company_phone'         => trim((string) ($company['office_phone'] ?? '')),
            'company_website'       => $websiteHtml,
            'client_name'           => $recipient,
            'client_address'        => nl2br(e(implode("\n", clientAddressLines($client)))),
            'case_number'           => (string) ($case['case_number'] ?? ''),
            'case_title'            => (string) ($case['title'] ?? ''),
            'date'                  => date('d/m/Y'),
            'fee_amount'            => $feeAmount,
            'total_fee'             => $feeAmount,
            'service_description'   => e($serviceDescription),
            'services_list'         => '<ul class="cl-services-list">' . implode('', $serviceLines) . '</ul>',
            'additional_notes'      => $additionalNotes !== '' ? nl2br(e($additionalNotes)) : '',
        ];
    }

    public static function replacePlaceholders(string $content, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    public static function renderHtml(int $caseId, array $sections, bool $embed = false): string
    {
        $case = CaseService::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $client = ClientService::getById((int) ($case['client_id'] ?? 0));
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        $company  = getCompanySettings();
        $context  = self::buildContext($case, $client, $company);
        $sections = self::normalizeSections($sections);

        foreach ($sections as $key => $content) {
            $sections[$key] = self::replacePlaceholders($content, $context);
        }

        return self::wrapDocument($company, $case, $client, $sections, $context, $embed);
    }

    public static function generateFile(int $caseId, array $sections): string
    {
        $html = self::renderHtml($caseId, $sections);
        self::saveCaseSections($caseId, $sections);

        $config = require __DIR__ . '/../config/config.php';
        $dir    = rtrim($config['upload']['path'], '/\\') . '/cases/' . $caseId . '/generated';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $htmlPath = $dir . '/client_letter.html';
        file_put_contents($htmlPath, $html);
        self::generatePdfFromHtml($htmlPath, $dir . '/client_letter.pdf');

        return $htmlPath;
    }

    public static function getGeneratedLetterPaths(int $caseId): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $dir    = rtrim($config['upload']['path'], '/\\') . '/cases/' . $caseId . '/generated';
        $html   = $dir . '/client_letter.html';
        $pdf    = $dir . '/client_letter.pdf';

        return [
            'html' => is_file($html) ? $html : null,
            'pdf'  => is_file($pdf) ? $pdf : null,
        ];
    }

    private static function generatePdfFromHtml(string $htmlPath, string $pdfPath): bool
    {
        if (!is_file($htmlPath)) {
            return false;
        }

        $wkhtml = trim((string) (shell_exec('where wkhtmltopdf 2>nul') ?: shell_exec('which wkhtmltopdf 2>/dev/null') ?: ''));
        if ($wkhtml === '') {
            return false;
        }

        $cmd = sprintf(
            'wkhtmltopdf --quiet --enable-local-file-access --print-media-type %s %s 2>&1',
            escapeshellarg($htmlPath),
            escapeshellarg($pdfPath)
        );
        exec($cmd, $output, $code);

        return $code === 0 && is_file($pdfPath);
    }

    private static function wrapDocument(
        array $company,
        array $case,
        array $client,
        array $sections,
        array $context,
        bool $embed = false
    ): string {
        $primary     = e($company['primary_color'] ?? '#3aafa9');
        $secondary   = e($company['secondary_color'] ?? '#00182c');
        $brandSans   = companyFontInlineStack($company);
        $companyName = e($context['company_name']);
        $logoUrl     = companyLogoUrl($company);

        $logoBlock = $logoUrl
            ? '<img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="cl-logo">'
            : '<div class="cl-logo-text">' . $companyName . '</div>';

        $bodyHtml = '';
        foreach (self::SECTION_KEYS as $key) {
            $content = trim($sections[$key] ?? '');
            if ($content === '' || ($key === 'additional_notes' && strip_tags($content) === '')) {
                continue;
            }
            $bodyHtml .= '<div class="cl-body-section">' . $content . '</div>';
        }

        $footerAddress = trim(strip_tags(str_replace(['<br />', '<br>', '<br/>'], "\n", $context['company_address'])));
        $footerLines   = array_filter(array_map('trim', explode("\n", $footerAddress)));

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<title>Client Letter — ' . e($case['case_number'] ?? '') . '</title>'
            . self::documentStyles($primary, $secondary, $brandSans, $embed)
            . '</head><body>'
            . ($embed ? '' : '<div class="cl-toolbar no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>')
            . '<div class="cl-document">'
            . '<header class="cl-letterhead">'
            . '<div class="cl-letterhead-top">'
            . '<div class="cl-letterhead-brand">' . $logoBlock . '</div>'
            . '<h1 class="cl-title">Client Letter</h1>'
            . '</div>'
            . '<div class="cl-address-row">'
            . '<div class="cl-from">'
            . '<div class="cl-from-name">' . $companyName . '</div>'
            . '<div class="cl-from-address">' . $context['company_address'] . '</div>'
            . '</div>'
            . '<div class="cl-to">'
            . '<div class="cl-to-label">To:</div>'
            . '<div class="cl-to-name">' . e($context['client_name']) . '</div>'
            . '<div class="cl-to-address">' . $context['client_address'] . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="cl-date-row"><span class="cl-date-label">Date:</span> <span class="cl-date-value">' . e($context['date']) . '</span></div>'
            . '</header>'
            . '<main class="cl-main">' . $bodyHtml . '</main>'
            . '</div>'
            . '<footer class="cl-page-footer">'
            . '<div class="cl-page-footer-inner">'
            . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="" class="cl-footer-logo">' : '')
            . '<div class="cl-footer-brand">'
            . '<div class="cl-footer-name">' . $companyName . '</div>'
            . '<div class="cl-footer-address">' . implode('<br>', array_map(static fn(string $l): string => e($l), $footerLines)) . '</div>'
            . '</div>'
            . '<div class="cl-page-num"></div>'
            . '</div>'
            . '</footer>'
            . '</body></html>';
    }

    public static function documentStyles(string $primary, string $secondary, string $brandSans, bool $embed = false): string
    {
        $bodyBg = $embed ? '#fff' : '#e5e7eb';

        return '<style>
            @page { size: A4; margin: 18mm 16mm 22mm 16mm; }
            * { box-sizing: border-box; }
            html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body {
                margin: 0;
                background: ' . $bodyBg . ';
                color: #111;
                font-family: Georgia, "Times New Roman", Times, serif;
                font-size: 11pt;
                line-height: 1.55;
            }
            .no-print, .cl-toolbar {
                padding: 12px 16px;
                background: #fff;
                border-bottom: 1px solid #ddd;
                text-align: center;
            }
            .cl-toolbar button {
                padding: 10px 22px;
                background: ' . $primary . ';
                color: #fff;
                border: none;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                font-family: ' . $brandSans . ';
            }
            .cl-document {
                max-width: 210mm;
                margin: 0 auto;
                background: #fff;
                padding: 14mm 16mm 28mm;
                min-height: 297mm;
            }
            .cl-letterhead { margin-bottom: 6mm; }
            .cl-letterhead-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 16px;
                margin-bottom: 10mm;
            }
            .cl-logo {
                display: block;
                max-height: 70px;
                max-width: 220px;
                width: auto;
                object-fit: contain;
            }
            .cl-logo-text {
                font-family: ' . $brandSans . ';
                font-size: 14pt;
                font-weight: 700;
                color: ' . $secondary . ';
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .cl-title {
                margin: 0;
                font-family: Georgia, "Times New Roman", Times, serif;
                font-size: 26pt;
                font-weight: 700;
                color: ' . $primary . ';
                text-transform: uppercase;
                letter-spacing: 0.05em;
                line-height: 1.05;
                text-align: right;
                flex-shrink: 0;
            }
            .cl-address-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 24px;
                margin-bottom: 8mm;
            }
            .cl-from { flex: 1; max-width: 48%; font-size: 10.5pt; line-height: 1.5; }
            .cl-from-name { font-weight: 700; margin-bottom: 4px; }
            .cl-to { flex: 1; max-width: 48%; text-align: right; font-size: 10.5pt; line-height: 1.5; }
            .cl-to-label { font-weight: 700; margin-bottom: 4px; }
            .cl-to-name { margin-bottom: 2px; }
            .cl-date-row { font-size: 10.5pt; margin-bottom: 6mm; }
            .cl-date-label { font-weight: 700; }
            .cl-date-value { text-decoration: underline; font-weight: 600; }
            .cl-main { text-align: justify; }
            .cl-body-section { margin-bottom: 4mm; }
            .cl-body-section p { margin: 0 0 10px; }
            .cl-body-section p:last-child { margin-bottom: 0; }
            .cl-body-section strong { font-weight: 700; }
            .cl-body-section ol, .cl-body-section ul {
                margin: 0 0 10px;
                padding-left: 22px;
            }
            .cl-body-section li { margin-bottom: 6px; }
            .cl-link { color: #2563eb; text-decoration: underline; }
            .cl-services-list { margin: 8px 0 12px; }
            .cl-page-footer {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                height: 22mm;
                background: #fff;
                border-top: 1px solid #c5c5c5;
                padding: 0 16mm;
                font-family: ' . $brandSans . ';
                font-size: 8.5pt;
                color: #333;
                z-index: 50;
            }
            .cl-page-footer-inner {
                display: flex;
                align-items: center;
                gap: 12px;
                max-width: 210mm;
                margin: 0 auto;
                height: 100%;
            }
            .cl-footer-logo {
                max-height: 36px;
                max-width: 100px;
                object-fit: contain;
                flex-shrink: 0;
            }
            .cl-footer-name {
                font-weight: 700;
                color: ' . $primary . ';
                text-transform: uppercase;
                letter-spacing: 0.03em;
                margin-bottom: 2px;
            }
            .cl-footer-address {
                font-family: Georgia, "Times New Roman", Times, serif;
                font-size: 9pt;
                line-height: 1.35;
                color: #111;
            }
            .cl-page-num::after { content: counter(page); }
            @media print {
                body { background: #fff; counter-reset: page; }
                .no-print, .cl-toolbar { display: none !important; }
                .cl-document {
                    max-width: none;
                    margin: 0;
                    padding: 0 0 24mm;
                    min-height: auto;
                    box-shadow: none;
                }
                .cl-page-footer { position: fixed; bottom: 0; }
                .cl-body-section { page-break-inside: avoid; }
            }
        </style>';
    }
}
