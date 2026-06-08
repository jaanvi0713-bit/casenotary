<?php

declare(strict_types=1);

/**
 * Professional notary client / engagement letters (HTML + optional PDF).
 */
class ClientLetterService
{
    public const SECTION_KEYS = [
        'introduction',
        'price_fee',
        'payment_terms',
        'service_information',
        'process_stages',
        'redress',
        'complaints_regulatory',
        'additional_notes',
        'signature',
    ];

    /** @var list<string> Keys removed from older letter drafts */
    private const LEGACY_SECTION_KEYS = [
        'fees_and_charges',
        'terms_and_conditions',
    ];

    public static function sectionLabels(): array
    {
        return [
            'introduction'          => 'Introduction',
            'price_fee'             => 'Price / Fee Information',
            'payment_terms'         => 'Payment Terms',
            'service_information'   => 'Service Information',
            'process_stages'        => 'Process / Key Stages',
            'redress'               => 'Redress Information',
            'complaints_regulatory' => 'Complaints and Regulatory Information',
            'additional_notes'      => 'Additional Notes',
            'signature'             => 'Signature / Closing',
        ];
    }

    public static function placeholderHelp(): array
    {
        return [
            '{{company_name}}'        => 'Company name (Settings)',
            '{{company_address}}'     => 'Company address',
            '{{company_email}}'       => 'Office email',
            '{{company_phone}}'       => 'Office phone',
            '{{company_website}}'     => 'Website (linked)',
            '{{client_name}}'         => 'Client or company name',
            '{{client_address}}'      => 'Client address',
            '{{case_number}}'         => 'Matter / case reference',
            '{{matter_reference}}'    => 'Same as case_number',
            '{{date}}'                => 'Letter date (DD/MM/YYYY)',
            '{{fee_amount}}'          => 'Total fee for this case',
            '{{service_description}}' => 'Primary service description',
            '{{services_list}}'       => 'Itemised services and fees (optional)',
            '{{additional_notes}}'    => 'Case notes or client instructions',
        ];
    }

    public static function builtinDefaultSections(): array
    {
        return [
            'introduction' => <<<'HTML'
<p>The service provided by me is that of a Notary Public carrying out all permitted notarial activities including, where appropriate, arranging legalisation of documents and sending them to their final destination. An essential part of a notary's role is to maintain and keep records. You can view details of how I handle your data on my website {{company_website}}.</p>
HTML,
            'price_fee' => <<<'HTML'
<p class="cl-heading">Price:</p>
<p>The fee for this transaction will be {{fee_amount}} which includes disbursements/legalisation fees/postage/consular agent fees/courier/travelling fees/translating costs</p>
<p>Some documents require legalisation before they will be accepted for use in the receiving jurisdiction by obtaining an apostille through the UK Foreign and Commonwealth Office and, for some countries, additional legalisation is required through the relevant embassy or consulate.</p>
<p>My fees are not subject to VAT.</p>
HTML,
            'payment_terms' => <<<'HTML'
<p>Payment can be made by cash/card/bank transfer. Payment of my fee and disbursements is due when the document has been prepared which I may retain pending payment in full.</p>
<p>Occasionally unforeseen or unusual issues arise during the course of the matter which may result in a revision of my fee estimate. Examples of this could include where additional documents are required to be notarised, additional translations or legalisations are needed to meet the requirements of the receiving jurisdiction, third party fees are adjusted to reflect external factors such as fuel price changes and so on. I will notify you of any changes in the fee estimate as soon as possible.</p>
HTML,
            'service_information' => <<<'HTML'
<p class="cl-heading">Service Information</p>
<p>Each notarial matter is different and the requirements and timescales will vary according to whether the client is a private individual or a company and according to the processing times of third parties such as the Foreign and Commonwealth Office, legalisation agents, translating agencies and couriers, etc. Some of the typical key stages are likely to include:</p>
HTML,
            'process_stages' => <<<'HTML'
<ol class="cl-numbered-list">
<li>Receiving and reviewing the documents to be notarised together with any instructions you may have received</li>
<li>Liaising with your legal advisors or other bodies to obtain the necessary documentation to deal with the document (e.g. information from Companies House or foreign registries, powers of attorney etc)</li>
<li>Checking the identity, capacity and authority of the person who is to sign the document</li>
<li>If a document is to be certified, checking with the issuing authorities that the document/award is genuine. In the case of academic awards, this would entail checking with the appropriate academic institutions.</li>
<li>Meeting with the signatory to verify their identity and to ascertain that they understand what they are signing and that they are doing so of their own free will and ensuring that the document is executed correctly</li>
<li>Drafting and affixing or endorsing a notarial certificate to the document</li>
<li>Arranging for the legalisation of the document as appropriate</li>
<li>Arranging for the storage of copies of all notarised documents in accordance with the requirements of the Notarial Practice Rules 2019</li>
</ol>
HTML,
            'redress' => <<<'HTML'
<p class="cl-heading">Redress</p>
<p>I am insured under a professional indemnity policy for at least £1,000,000.00.</p>
HTML,
            'complaints_regulatory' => <<<'HTML'
<p class="cl-heading">Complaints and Regulatory Information</p>
<ol class="cl-numbered-list">
<li>My notarial practice is regulated through the Faculty Office of the Archbishop of Canterbury:<br>
<span class="cl-address-block">The Faculty Office<br>
1, The Sanctuary<br>
Westminster<br>
London SW1P 3JT<br>
Telephone 020 7222 5381<br>
Email Faculty.office@1thesanctuary.com<br>
Website www.facultyoffice.org.uk</span></li>
<li>If you are dissatisfied about the service you have received please do not hesitate to contact me.</li>
<li>If we are unable to resolve the matter you may then complain to the Notaries Society of which I am a member, who have a Complaints Procedure which is approved by the Faculty Office. This procedure is free to use and is designed to provide a quick resolution to any dispute.</li>
<li>In that case please write (but do not enclose any original documents) with full details of your complaint to :-<br>
<span class="cl-address-block">The Secretary of The Notaries Society<br>
P O Box 7655<br>
Milton Keynes MK11 9NR<br>
Email secretary@thenotariessociety.org.uk Tel :01908 803527</span><br>
If you have any difficulty in making a complaint in writing please do not hesitate to call the Notaries Society/the Faculty Office for assistance.</li>
<li>Finally, even if you have your complaint considered under the Notaries Society Approved Complaints Procedure, you may at the end of that procedure, or after a period of 8 weeks from the date you first notified me that you were dissatisfied, make your complaint to the Legal Ombudsman, if you are not happy with the result :<br>
<span class="cl-address-block">Legal Ombudsman<br>
P O Box 6806<br>
Wolverhampton WV1 9WJ<br>
Tel : 0300 555 0333<br>
Email : enquiries@legalombudsman.org.uk<br>
Website : www.legalombudsman.org.uk</span></li>
<li>If you decide to make a complaint to the Legal Ombudsman, you must refer your matter to the Legal Ombudsman within six months from the conclusion of the complaint process.</li>
</ol>
HTML,
            'additional_notes' => '',
            'signature' => '',
        ];
    }

    public static function syncDefaultTemplateContent(): void
    {
        if (!Database::tableExists('client_letter_templates')) {
            return;
        }

        $json = json_encode(self::builtinDefaultSections(), JSON_UNESCAPED_UNICODE);
        Database::query(
            'UPDATE client_letter_templates SET sections = ?, updated_at = NOW() WHERE is_default = 1',
            [$json]
        );
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

        return is_array($decoded) ? self::normalizeSections($decoded) : $defaults;
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

        foreach (self::LEGACY_SECTION_KEYS as $legacy) {
            $field = 'letter_' . $legacy;
            if (array_key_exists($field, $post)) {
                $sections[$legacy] = trim((string) $post[$field]);
            }
        }

        return self::normalizeSections($sections);
    }

    /** @param array<string, string> $sections */
    private static function migrateLegacySectionKeys(array $sections): array
    {
        if (!empty($sections['fees_and_charges'])) {
            if (empty($sections['price_fee'])) {
                $sections['price_fee'] = $sections['fees_and_charges'];
            }
            if (empty($sections['payment_terms']) && str_contains($sections['fees_and_charges'], 'Payment')) {
                $sections['payment_terms'] = $sections['fees_and_charges'];
            }
        }

        if (!empty($sections['terms_and_conditions']) && empty($sections['payment_terms'])) {
            $sections['payment_terms'] = $sections['terms_and_conditions'];
        }

        if (!empty($sections['complaints_regulatory']) && empty($sections['redress'])
            && stripos($sections['complaints_regulatory'], 'Redress') !== false) {
            if (preg_match('/<p[^>]*>\s*<strong>\s*Redress\s*<\/strong>.*?<\/p>\s*<p>.*?<\/p>/is', $sections['complaints_regulatory'], $m)) {
                $sections['redress'] = $m[0];
            }
        }

        if (!empty($sections['service_information']) && empty($sections['process_stages'])
            && str_contains($sections['service_information'], '<ol')) {
            if (preg_match('/<ol[^>]*>.*?<\/ol>/is', $sections['service_information'], $m)) {
                $sections['process_stages'] = $m[0];
                $sections['service_information'] = preg_replace('/<ol[^>]*>.*?<\/ol>/is', '', $sections['service_information']) ?? $sections['service_information'];
            }
        }

        return $sections;
    }

    public static function normalizeSections(array $sections): array
    {
        $sections = self::migrateLegacySectionKeys($sections);
        $defaults = self::builtinDefaultSections();
        $out      = [];

        foreach (self::SECTION_KEYS as $key) {
            if (array_key_exists($key, $sections)) {
                $out[$key] = trim((string) $sections[$key]);
            } else {
                $out[$key] = $defaults[$key] ?? '';
            }
        }

        return $out;
    }

    public static function buildContext(array $case, array $client, ?array $company = null): array
    {
        $company = $company ?? getCompanySettings();
        $billing = CaseService::getCaseBilling($case);
        $serviceLines = [];

        $nonVatRate = (float) ($billing['non_vat_rate'] ?? 0);
        foreach ($billing['non_vat'] ?? [] as $row) {
            $net  = (float) $row['net'];
            $rate = round($net * $nonVatRate / 100, 2);
            $line = '<li>' . e($row['type']) . ' (Non-VAT) — ' . formatCurrency($net);
            if ($rate > 0) {
                $line .= ' + rate ' . formatCurrency($rate);
            }
            $serviceLines[] = $line . ' = ' . formatCurrency($net + $rate) . '</li>';
        }
        foreach ($billing['vat'] ?? [] as $row) {
            $net = (float) $row['net'];
            $vat = round($net * (float) $billing['vat_rate'] / 100, 2);
            $serviceLines[] = '<li>' . e($row['type']) . ' (VAT) — ' . formatCurrency($net) . ' + VAT ' . formatCurrency($vat) . '</li>';
        }

        if ($serviceLines === []) {
            $serviceLines[] = '<li>' . e($case['service_type'] ?? 'Notary services') . ' — '
                . formatCurrency((float) ($billing['totals']['grand_total'] ?? $case['service_fee'] ?? 0)) . '</li>';
        }

        $website = trim((string) ($company['company_website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }
        $websiteDisplay = $website !== '' ? preg_replace('#^https?://#i', '', $website) : '';
        $websiteHtml    = $websiteDisplay !== ''
            ? '<a href="' . e($website) . '" class="cl-link">' . e($websiteDisplay) . '</a>'
            : e(companyBrandName($company));

        $recipient = trim((string) ($client['company_name'] ?? ''));
        if ($recipient === '') {
            $recipient = clientFullName($client) ?: 'Client';
        }

        $feeAmount          = formatCurrency((float) (CaseService::getCaseBilling($case)['totals']['grand_total'] ?? $case['service_fee'] ?? 0));
        $serviceDescription = trim((string) ($case['service_type'] ?? $case['title'] ?? 'Notary services'));
        $instructions       = trim((string) ($case['client_instructions'] ?? ''));
        $additionalNotes    = trim((string) ($case['description'] ?? ''));
        if ($additionalNotes === '' && $instructions !== '') {
            $additionalNotes = $instructions;
        }

        $caseNumber = (string) ($case['case_number'] ?? '');
        $caseTitle  = (string) ($case['title'] ?? '');

        $legalName = trim((string) ($company['company_legal_name'] ?? ''));
        if ($legalName === '') {
            $brand = companyBrandName($company);
            $legalName = strtoupper($brand);
            if ($legalName !== '' && !preg_match('/\b(LTD|LIMITED|LLC|PLC)\b/i', $legalName)) {
                $legalName .= ' LTD';
            }
        }

        $contactParts = array_filter([
            trim((string) ($company['office_email'] ?? '')) !== '' ? 'Email: ' . e($company['office_email']) : '',
            trim((string) ($company['office_phone'] ?? '')) !== '' ? 'Tel: ' . e($company['office_phone']) : '',
            trim((string) ($company['registration_number'] ?? '')) !== '' ? 'Reg: ' . e($company['registration_number']) : '',
        ]);

        return [
            'company_name'        => companyBrandName($company),
            'company_legal_name'  => $legalName,
            'company_address'     => companyAddressHtml($company),
            'company_contact'     => implode('<br>', $contactParts),
            'company_email'       => trim((string) ($company['office_email'] ?? '')),
            'company_phone'       => trim((string) ($company['office_phone'] ?? '')),
            'company_website'     => $websiteHtml,
            'client_name'         => $recipient,
            'client_address'      => nl2br(e(implode("\n", clientAddressLines($client)))),
            'case_number'         => e($caseNumber),
            'case_title'          => e($caseTitle),
            'matter_reference'    => e($caseNumber),
            'case_reference'      => e($caseNumber),
            'date'                => date('d/m/Y'),
            'fee_amount'          => $feeAmount,
            'total_fee'           => $feeAmount,
            'service_description' => e($serviceDescription),
            'services_list'       => '<ul class="cl-services-list">' . implode('', $serviceLines) . '</ul>',
            'additional_notes'    => $additionalNotes !== '' ? nl2br(e($additionalNotes)) : '',
        ];
    }

    public static function replacePlaceholders(string $content, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
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

    private static function sectionIsEmpty(string $key, string $content): bool
    {
        if ($content === '') {
            return true;
        }

        if (in_array($key, ['additional_notes', 'signature'], true) && strip_tags($content) === '') {
            return true;
        }

        return false;
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
        $legalName   = e($context['company_legal_name']);
        $logoUrl     = companyLogoUrl($company);

        $logoBlock = $logoUrl
            ? '<img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="cl-logo">'
            : '<div class="cl-logo-text">' . $companyName . '</div>';

        $contactBlock = ($context['company_contact'] ?? '') !== ''
            ? '<div class="cl-from-contact">' . $context['company_contact'] . '</div>'
            : '';

        $matterLine = ($context['case_number'] ?? '') !== ''
            ? '<div class="cl-matter-ref"><span class="cl-matter-label">Matter ref:</span> ' . $context['case_number']
                . ($context['case_title'] !== '' ? ' — <em>' . $context['case_title'] . '</em>' : '')
                . '</div>'
            : '';

        $bodyHtml = '';
        foreach (self::SECTION_KEYS as $key) {
            $content = trim($sections[$key] ?? '');
            if (self::sectionIsEmpty($key, $content)) {
                continue;
            }
            $bodyHtml .= '<section class="cl-body-section" data-section="' . e($key) . '">' . $content . '</section>';
        }

        $runningFooter = '<div class="cl-running-footer">'
            . '<table class="cl-running-footer-table" width="100%"><tr>'
            . '<td class="cl-running-footer-left">'
            . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="" class="cl-running-footer-logo">' : '')
            . '<div class="cl-running-footer-legal">' . $legalName . '</div>'
            . '<div class="cl-running-footer-address">' . $context['company_address'] . '</div>'
            . '</td>'
            . '<td class="cl-running-footer-page"><span class="cl-page-num"></span></td>'
            . '</tr></table></div>';

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
            . $contactBlock
            . '</div>'
            . '<div class="cl-to">'
            . '<div class="cl-to-label">To:</div>'
            . '<div class="cl-to-name">' . e($context['client_name']) . '</div>'
            . '<div class="cl-to-address">' . $context['client_address'] . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="cl-date-row"><span class="cl-date-label">Date:</span> <span class="cl-date-value">' . e($context['date']) . '</span></div>'
            . $matterLine
            . '</header>'
            . '<main class="cl-main">' . $bodyHtml
            . '<div class="cl-end-branding">'
            . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="" class="cl-end-logo">' : '')
            . '<div class="cl-end-legal-name">' . $legalName . '</div>'
            . '<div class="cl-end-address">' . $context['company_address'] . '</div>'
            . '</div>'
            . '</main>'
            . '</div>'
            . $runningFooter
            . '</body></html>';
    }

    public static function documentStyles(string $primary, string $secondary, string $brandSans, bool $embed = false): string
    {
        $bodyBg = $embed ? '#fff' : '#e5e7eb';

        return '<style>
            @page {
                size: A4;
                margin: 18mm 16mm 28mm 16mm;
            }
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
                padding: 12mm 14mm 32mm;
                min-height: 297mm;
            }
            .cl-letterhead { margin-bottom: 5mm; }
            .cl-letterhead-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 16px;
                margin-bottom: 8mm;
            }
            .cl-logo {
                display: block;
                max-height: 88px;
                max-width: 260px;
                width: auto;
                object-fit: contain;
            }
            .cl-logo-text {
                font-family: ' . $brandSans . ';
                font-size: 13pt;
                font-weight: 700;
                color: ' . $secondary . ';
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .cl-title {
                margin: 0;
                font-family: Georgia, "Times New Roman", Times, serif;
                font-size: 28pt;
                font-weight: 700;
                color: ' . $primary . ';
                text-transform: uppercase;
                letter-spacing: 0.06em;
                line-height: 1;
                text-align: right;
                flex-shrink: 0;
            }
            .cl-address-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 20px;
                margin-bottom: 6mm;
            }
            .cl-from { flex: 1; max-width: 50%; font-size: 10.5pt; line-height: 1.5; }
            .cl-from-name { font-weight: 700; margin-bottom: 3px; }
            .cl-from-address, .cl-from-contact { margin-top: 2px; }
            .cl-from-contact { font-size: 9.5pt; color: #333; margin-top: 6px; line-height: 1.4; }
            .cl-to { flex: 1; max-width: 50%; text-align: right; font-size: 10.5pt; line-height: 1.5; }
            .cl-to-label { font-weight: 700; margin-bottom: 4px; }
            .cl-date-row { font-size: 10.5pt; margin-bottom: 3mm; }
            .cl-date-label { font-weight: 700; }
            .cl-date-value { text-decoration: underline; font-weight: 600; }
            .cl-matter-ref { font-size: 10pt; color: #333; margin-bottom: 5mm; }
            .cl-matter-label { font-weight: 700; }
            .cl-main { text-align: justify; }
            .cl-body-section {
                margin-bottom: 5mm;
                page-break-inside: avoid;
            }
            .cl-body-section p { margin: 0 0 10px; }
            .cl-heading {
                font-weight: 700;
                margin: 12px 0 8px !important;
                page-break-after: avoid;
            }
            .cl-numbered-list {
                margin: 0 0 12px;
                padding-left: 26px;
                list-style-type: decimal;
            }
            .cl-numbered-list li {
                margin-bottom: 8px;
                padding-left: 4px;
            }
            .cl-address-block {
                display: block;
                margin: 6px 0 4px 0;
                line-height: 1.45;
            }
            .cl-link { color: #1d4ed8; text-decoration: underline; }
            .cl-services-list { margin: 8px 0 12px; padding-left: 24px; }
            .cl-end-branding {
                margin-top: 16mm;
                padding-top: 6mm;
                page-break-inside: avoid;
            }
            .cl-end-logo {
                display: block;
                max-height: 70px;
                max-width: 230px;
                object-fit: contain;
                margin-bottom: 8px;
            }
            .cl-end-legal-name {
                font-family: ' . $brandSans . ';
                font-weight: 700;
                font-size: 11pt;
                color: ' . $primary . ';
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin-bottom: 6px;
            }
            .cl-end-address { font-size: 10.5pt; line-height: 1.45; }
            .cl-running-footer {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                height: 24mm;
                background: #fff;
                border-top: 1px solid #b8b8b8;
                padding: 3mm 16mm 0;
                font-size: 8pt;
                z-index: 100;
            }
            .cl-running-footer-table { width: 100%; border-collapse: collapse; }
            .cl-running-footer-left { vertical-align: middle; width: 85%; }
            .cl-running-footer-page { vertical-align: middle; text-align: right; width: 15%; color: #555; }
            .cl-running-footer-logo {
                display: inline-block;
                vertical-align: middle;
                max-height: 40px;
                max-width: 110px;
                margin-right: 10px;
                object-fit: contain;
            }
            .cl-running-footer-legal {
                display: inline-block;
                vertical-align: middle;
                font-family: ' . $brandSans . ';
                font-weight: 700;
                color: ' . $primary . ';
                text-transform: uppercase;
                font-size: 8.5pt;
                margin-right: 8px;
            }
            .cl-running-footer-address {
                display: inline-block;
                vertical-align: middle;
                font-family: Georgia, serif;
                font-size: 7.5pt;
                line-height: 1.3;
                color: #333;
            }
            .cl-page-num::after { content: counter(page); }
            @media print {
                body { background: #fff; counter-reset: page; }
                .no-print, .cl-toolbar { display: none !important; }
                .cl-document {
                    max-width: none;
                    margin: 0;
                    padding: 0 0 28mm;
                    min-height: auto;
                }
                .cl-running-footer {
                    position: fixed;
                    bottom: 0;
                }
                .cl-body-section { page-break-inside: avoid; }
                .cl-heading { page-break-after: avoid; }
            }
        </style>';
    }

    public static function lettersTableExists(): bool
    {
        return Database::tableExists('case_client_letters');
    }

    /** @return list<array<string, mixed>> */
    public static function listForCase(int $caseId, bool $publishedOnly = false): array
    {
        if (!self::lettersTableExists()) {
            return [];
        }

        $sql = 'SELECT l.*, u.first_name AS creator_first, u.last_name AS creator_last,
                       cs.case_number, cs.title AS case_title
                FROM case_client_letters l
                JOIN cases cs ON cs.id = l.case_id
                LEFT JOIN users u ON u.id = l.created_by
                WHERE l.case_id = ?';
        $params = [$caseId];

        if ($publishedOnly) {
            $sql .= ' AND l.published_to_portal = 1 AND l.saved_to_record = 1';
        }

        $sql .= ' ORDER BY l.created_at DESC, l.version DESC';

        return Database::fetchAll($sql, $params);
    }

    public static function getById(int $letterId): ?array
    {
        if ($letterId <= 0 || !self::lettersTableExists()) {
            return null;
        }

        return Database::fetch(
            'SELECT l.*, cs.case_number, cs.title AS case_title
             FROM case_client_letters l
             JOIN cases cs ON cs.id = l.case_id
             WHERE l.id = ?',
            [$letterId]
        ) ?: null;
    }

    public static function getPublishedForClientCase(int $caseId, int $clientId): array
    {
        if (!self::lettersTableExists()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT l.*, cs.case_number, cs.title AS case_title
             FROM case_client_letters l
             JOIN cases cs ON cs.id = l.case_id
             WHERE l.case_id = ? AND l.client_id = ? AND l.published_to_portal = 1 AND l.saved_to_record = 1
             ORDER BY l.created_at DESC',
            [$caseId, $clientId]
        );
    }

    public static function ensureGeneratedDraft(int $caseId, string $instructions = '', ?array $sections = null): void
    {
        $paths = self::getGeneratedLetterPaths($caseId);
        if ($paths['html'] || $paths['pdf']) {
            return;
        }

        if ($instructions !== '' && Database::columnExists('cases', 'client_instructions')) {
            Database::query(
                'UPDATE cases SET client_instructions = ?, updated_at = NOW() WHERE id = ?',
                [$instructions, $caseId]
            );
        }

        $sections = $sections ?? self::getSectionsForCase($caseId);
        self::generateFile($caseId, $sections);
    }

    /**
     * Copy current generated draft into a saved client letter record.
     */
    public static function saveToClientRecord(
        int $caseId,
        int $adminId,
        bool $replaceCurrent = false
    ): int {
        if (!self::lettersTableExists()) {
            throw new RuntimeException('Run: php admin/sql/migrate_case_client_letters.php');
        }

        $case = CaseService::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $paths = self::getGeneratedLetterPaths($caseId);
        if (!$paths['html'] && !$paths['pdf']) {
            throw new RuntimeException('Generate the letter first.');
        }

        $clientId = (int) ($case['client_id'] ?? 0);
        $title    = 'Client Letter — ' . ($case['case_number'] ?? 'Case');

        if ($replaceCurrent) {
            Database::query(
                'UPDATE case_client_letters SET is_current = 0 WHERE case_id = ? AND is_current = 1',
                [$caseId]
            );
        }

        $prev = Database::fetch(
            'SELECT id, version, version_group_id FROM case_client_letters
             WHERE case_id = ? ORDER BY version DESC, id DESC LIMIT 1',
            [$caseId]
        );

        $version      = $prev ? ((int) $prev['version'] + 1) : 1;
        $versionGroup = $prev ? (int) ($prev['version_group_id'] ?: $prev['id']) : null;

        $letterId = insertTableRow('case_client_letters', [
            'case_id'             => $caseId,
            'client_id'           => $clientId,
            'title'               => $title,
            'version'             => $version,
            'version_group_id'    => $versionGroup,
            'is_current'          => 1,
            'saved_to_record'     => 1,
            'published_to_portal' => 0,
            'created_by'          => $adminId,
        ], false);

        if ($versionGroup === null) {
            Database::query(
                'UPDATE case_client_letters SET version_group_id = ? WHERE id = ?',
                [$letterId, $letterId]
            );
        }

        [$pdfRel, $htmlRel] = self::copyGeneratedToLetterStorage($caseId, $letterId, $paths);

        Database::query(
            'UPDATE case_client_letters SET pdf_path = ?, html_path = ? WHERE id = ?',
            [$pdfRel, $htmlRel, $letterId]
        );

        return $letterId;
    }

    /** @param array{html: ?string, pdf: ?string} $paths */
    private static function copyGeneratedToLetterStorage(int $caseId, int $letterId, array $paths): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $base   = rtrim($config['upload']['path'], '/\\');
        $destDir = $base . '/cases/' . $caseId . '/letters';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $pdfRel  = null;
        $htmlRel = null;
        $prefix  = 'letter_' . $letterId;

        if ($paths['pdf'] && is_file($paths['pdf'])) {
            $dest = $destDir . '/' . $prefix . '.pdf';
            copy($paths['pdf'], $dest);
            $pdfRel = 'cases/' . $caseId . '/letters/' . $prefix . '.pdf';
        }

        if ($paths['html'] && is_file($paths['html'])) {
            $dest = $destDir . '/' . $prefix . '.html';
            copy($paths['html'], $dest);
            $htmlRel = 'cases/' . $caseId . '/letters/' . $prefix . '.html';
        }

        return [$pdfRel, $htmlRel];
    }

    public static function publishToPortal(int $letterId): void
    {
        if (!self::lettersTableExists()) {
            throw new RuntimeException('Run: php admin/sql/migrate_case_client_letters.php');
        }

        $letter = self::getById($letterId);
        if (!$letter || empty($letter['saved_to_record'])) {
            throw new RuntimeException('Save the letter to the client record before publishing.');
        }

        if (empty($letter['pdf_path']) && empty($letter['html_path'])) {
            throw new RuntimeException('Letter file not found.');
        }

        Database::query(
            'UPDATE case_client_letters SET published_to_portal = 1 WHERE id = ?',
            [$letterId]
        );
    }

    public static function unpublishFromPortal(int $letterId): void
    {
        if (!self::lettersTableExists()) {
            return;
        }

        Database::query(
            'UPDATE case_client_letters SET published_to_portal = 0 WHERE id = ?',
            [$letterId]
        );
    }

    public static function deleteLetter(int $letterId): void
    {
        if (!self::lettersTableExists()) {
            return;
        }

        $letter = self::getById($letterId);
        if (!$letter) {
            return;
        }

        $config = require __DIR__ . '/../config/config.php';
        $base   = rtrim($config['upload']['path'], '/\\');

        foreach (['pdf_path', 'html_path'] as $col) {
            if (!empty($letter[$col])) {
                $path = $base . '/' . ltrim((string) $letter[$col], '/');
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        Database::query('DELETE FROM case_client_letters WHERE id = ?', [$letterId]);
    }

    public static function getDownloadPath(array $letter): ?string
    {
        if (!empty($letter['pdf_path'])) {
            return (string) $letter['pdf_path'];
        }

        if (!empty($letter['html_path'])) {
            return (string) $letter['html_path'];
        }

        return null;
    }

    public static function getCurrentSavedLetter(int $caseId): ?array
    {
        if (!self::lettersTableExists()) {
            return null;
        }

        return Database::fetch(
            'SELECT * FROM case_client_letters
             WHERE case_id = ? AND saved_to_record = 1 AND is_current = 1
             ORDER BY id DESC LIMIT 1',
            [$caseId]
        ) ?: null;
    }
}
