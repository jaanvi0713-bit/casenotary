<?php

declare(strict_types=1);

class AssistantPracticeFaq
{
    public static function matches(string $message): bool
    {
        return self::matchTopic($message) !== null;
    }

    public static function matchTopic(string $message): ?string
    {
        $lower = strtolower(trim($message));

        if ($lower === '') {
            return null;
        }

        if (preg_match(
            '/\b(how (?:do|should) i prepare|prepare (?:my )?document|before (?:the )?appointment|what (?:to |should i )bring)\b/',
            $lower
        ) && preg_match('/\b(document|appointment|signing|notar|visit)\b/', $lower)) {
            return 'document_preparation';
        }

        if (preg_match('/\bdo i need (?:to )?(?:supply|bring|provide|arrange)\b.*\bwitness/', $lower)
            || (preg_match('/\b(witness|witnesses)\b/', $lower)
                && preg_match('/\b(supply|bring|provide|need|required|own|arrange)\b/', $lower))) {
            return 'witnesses';
        }

        if (preg_match('/\b(which|what) documents?\b.*\b(require|need)\b.*\bnotar/', $lower)
            || preg_match('/\bdocuments? (?:that |which )?require\s+notar/', $lower)
            || preg_match('/\brequire\s+notarization\b/', $lower)
            || preg_match('/\bwhat (?:kinds?|types?) of documents?.*notar/', $lower)) {
            return 'documents_notarization';
        }

        if (preg_match('/\bforeign passports?\b/', $lower)
            || preg_match('/\bpassport\b.*\b(foreign|international|overseas|non[- ]uk|non[- ]us|accepted|use)\b/', $lower)
            || preg_match('/\b(can|may|are).*\bforeign\b.*\bpassport/', $lower)) {
            return 'foreign_passport';
        }

        if (preg_match(
            '/\b(identification|identity document|id document|forms? of id|acceptable id|legally accepted|proof of identity|valid id)\b/',
            $lower
        ) || preg_match('/\bwhat (?:id|identification|forms? of identification)\b/', $lower)) {
            return 'identification';
        }

        if (preg_match('/\bmobile notary\b/', $lower)
            || preg_match('/\bbook\b.*\b(mobile|traveling|travelling|visit|home|office)\b.*\bnotary/', $lower)
            || preg_match('/\bnotary\b.*\b(?:in my area|near me|my area|come to me)\b/', $lower)
            || preg_match('/\bhow (?:do|can) i book\b.*\bnotary/', $lower)) {
            return 'mobile_booking';
        }

        if (preg_match('/\b(24\/?7|24[- ]hour|remote online notary|\bron\b|online notarization|video notar|virtual notar)/', $lower)
            || preg_match('/\bis there\b.*\b(remote|online)\b.*\bnotary/', $lower)) {
            return 'ron_availability';
        }

        if (preg_match('/\b(state notary fees?|statutory notary fees?|standard (?:state )?notary fees?|maximum notary fees?|notary fee schedule)\b/', $lower)
            || preg_match('/\bwhat are\b.*\b(?:the )?(?:standard |state )?notary fees?\b/', $lower)) {
            return 'state_fees';
        }

        return null;
    }

    /** @return array{content: string} */
    public static function handle(string $message): array
    {
        $topic = self::matchTopic($message) ?? 'documents_notarization';

        return match ($topic) {
            'witnesses' => ['content' => self::witnessesAnswer()],
            'identification' => ['content' => self::identificationAnswer()],
            'foreign_passport' => ['content' => self::foreignPassportAnswer()],
            'mobile_booking' => ['content' => self::mobileBookingAnswer()],
            'ron_availability' => ['content' => self::ronAvailabilityAnswer()],
            'state_fees' => ['content' => self::stateFeesAnswer()],
            'document_preparation' => ['content' => self::documentPreparationAnswer()],
            default => ['content' => self::documentsNotarizationAnswer()],
        };
    }

    private static function isUsPractice(): bool
    {
        $country = strtolower(trim((string) (getCompanySettings()['country'] ?? '')));

        return str_contains($country, 'united states')
            || str_contains($country, 'u.s.')
            || preg_match('/\b(us|usa|u\.s\.a\.)\b/', $country);
    }

    private static function companyLine(): string
    {
        $settings = getCompanySettings();
        $name = companyBrandName($settings);
        $parts = ['**' . $name . '**'];

        $phone = trim((string) ($settings['phone'] ?? ''));
        $email = trim((string) ($settings['email'] ?? ''));
        if ($phone !== '') {
            $parts[] = 'tel. ' . $phone;
        }
        if ($email !== '') {
            $parts[] = $email;
        }

        return implode(' — ', $parts);
    }

    private static function documentsNotarizationAnswer(): string
    {
        $us = self::isUsPractice();

        $lines = [
            '**Which documents require notarization?**',
            '',
            'There is no single list for every situation — the **receiving institution** (bank, court, embassy, registry, university, etc.) decides what must be notarized. Common examples include:',
            '',
            '• **Affidavits** and sworn statements (jurat)',
            '• **Powers of attorney** and mandates',
            '• **Deeds**, mortgages, and property transfer papers (where required)',
            '• **Statutory declarations** and consent letters',
            '• **Corporate documents** (resolutions, certificates of good standing, director appointments)',
            '• **Academic / professional qualifications** for use abroad',
            '• **Contracts** and commercial instruments for overseas parties',
            '• **Certified copies** of passports, degrees, or company records for foreign use',
        ];

        if ($us) {
            $lines[] = '• **Loan signing packages** and real-estate closings (often via a signing agent)';
        } else {
            $lines[] = '• Documents for **apostille or legalisation** before use in another country';
        }

        $lines[] = '';
        $lines[] = '**Practical tip:** Ask the client *who will receive the document* and whether they specified acknowledgment, jurat, certified copy, or authentication. If unsure, review the instruction letter from the foreign lawyer, bank, or embassy before booking.';
        $lines[] = '';
        $lines[] = 'You can start a matter and attach the instruction letter in '
            . assistantAdminLink('pages/cases.php', 'Cases')
            . ', or use **start intake** to capture document type early.';

        return implode("\n", $lines);
    }

    private static function witnessesAnswer(): string
    {
        $lines = [
            '**Do clients need to supply their own witnesses?**',
            '',
            '**Usually it depends on the document, not the notary alone.**',
            '',
            '• For a standard **acknowledgment** or **jurat** before a notary, witnesses are **often not required** unless the document text or governing law says so.',
            '• **Wills, deeds, and certain affidavits** may require one or two independent witnesses in addition to notarization — the client should check the draft or their solicitor’s instructions.',
            '• Witnesses must typically be **adults, disinterested** (not benefiting from the document), and able to **appear in person** when the document is signed.',
            '• A **credible identifying witness** is different: that person helps the notary verify identity when acceptable ID is unavailable, under strict rules.',
        ];

        $lines[] = '';
        $lines[] = '**Office policy:** Confirm whether ' . self::companyLine() . ' provides witnesses for specific services. If not, tell the client to bring the required number of witnesses, with photo ID, to the appointment.';
        $lines[] = '';
        $lines[] = 'Use **start intake** to record witness requirements on the matter before scheduling in '
            . assistantAdminLink('pages/appointments.php', 'Appointments') . '.';

        return implode("\n", $lines);
    }

    private static function identificationAnswer(): string
    {
        $us = self::isUsPractice();

        $lines = [
            '**What forms of identification are legally accepted?**',
            '',
            'The notary must be **reasonably satisfied** of the signer’s identity. Acceptable ID is usually a **current government-issued photo ID** that is intact, unexpired, and matches the person appearing.',
            '',
            '**Commonly accepted (subject to your governing rules):**',
            '',
            '• **Passport** (UK, US, or foreign — see separate guidance if foreign)',
            '• **Photocard driving licence**',
            '• **National identity card** (where issued)',
        ];

        if ($us) {
            $lines[] = '• **State-issued ID card** or **military ID**';
            $lines[] = '• In some states, **credible identifying witnesses** when primary ID is unavailable';
        } else {
            $lines[] = '• **Biometric residence permit** or other Home Office photo documents where applicable';
            $lines[] = '• **Credible witness** identification only where your rules and risk assessment allow';
        }

        $lines[] = '';
        $lines[] = '**Also expect:**';
        $lines[] = '• Name on the ID should match the document (or bring linking evidence for name changes)';
        $lines[] = '• **Proof of address** may be required for AML / customer due diligence';
        $lines[] = '• **Minors** — verify capacity; extra ID or parental consent may apply';
        $lines[] = '';
        $lines[] = 'If ID is borderline, do not notarize until satisfied — escalate to a senior notary or decline the act.';

        return implode("\n", $lines);
    }

    private static function foreignPassportAnswer(): string
    {
        $lines = [
            '**Can foreign passports be used for identification?**',
            '',
            '**Yes, in most cases** — a valid **foreign passport** with a photo is widely accepted for notarial identification, provided you are satisfied it is **genuine, current, and belongs to the signer**.',
            '',
            '**Before accepting:**',
            '',
            '• Compare the photo and physical appearance carefully',
            '• Check expiry date and security features',
            '• Confirm the name matches the document being signed',
            '• Complete your **AML / sanctions** checks as required by your practice',
            '• Note the issuing country in your journal entry',
            '',
            '**Caveats:**',
            '',
            '• Some institutions abroad may later require a **certified copy** of the passport rather than relying on notary memory alone',
            '• If the passport is not in English, consider whether a **certified translation** of key fields is needed for the receiving jurisdiction',
            '• Damaged, expired, or provisional documents should be rejected',
            '',
            'When in doubt, request a second form of ID or use a **credible witness** only if permitted by your governing rules.',
        ];

        return implode("\n", $lines);
    }

    private static function mobileBookingAnswer(): string
    {
        $appointments = assistantAdminLink('pages/appointments.php', 'Appointments');
        $intake = assistantAdminLink('pages/assistant.php', 'AI Assistant');

        $lines = [
            '**How to book a mobile notary (travel / visit) appointment**',
            '',
            'For staff answering client enquiries:',
            '',
            '1. **Capture details** — location, document type, number of signers, language, and urgency (use **start intake** in the assistant or create a case).',
            '2. **Check notary availability** — open ' . $appointments . ' and schedule with location set to the client address or “Mobile visit”.',
            '3. **Confirm fees** — travel time, mileage, after-hours surcharge, and number of notarial acts.',
            '4. **Send confirmation** — appointment time, what to bring (ID, witnesses if needed, unsigned documents), and your cancellation policy.',
        ];

        $lines[] = '';
        $lines[] = '**Client-facing script:** “Contact ' . self::companyLine() . ' with your postcode and document type. We will confirm whether a mobile appointment is available in your area and provide a fee estimate.”';
        $lines[] = '';
        $lines[] = 'From the portal you can also say _Schedule appointment for [client] tomorrow at 2pm_ or open ' . $intake . ' for intake.';

        return implode("\n", $lines);
    }

    private static function ronAvailabilityAnswer(): string
    {
        $us = self::isUsPractice();

        $lines = [
            '**Remote / online notary availability**',
            '',
        ];

        if ($us) {
            $lines[] = '**Remote Online Notarization (RON)** is permitted in many US states, but rules vary by state (platform, identity proofing, audio-video recording, journal, and seal requirements).';
            $lines[] = '';
            $lines[] = '• **24/7 service** is a business choice — not a legal default. Confirm whether your commission, insurer, and platform allow after-hours acts.';
            $lines[] = '• Verify the signer’s **state** and whether the receiving party accepts RON for that document type.';
            $lines[] = '• Use only **approved RON platforms** and follow your state’s retention rules.';
        } else {
            $lines[] = 'In the **UK**, traditional notarial acts are normally performed **in person**. Full US-style **Remote Online Notarization (RON)** is not the standard UK notary model.';
            $lines[] = '';
            $lines[] = '• You may offer **video consultations** to discuss requirements, but the notarial act itself is typically completed face-to-face unless a specific scheme applies.';
            $lines[] = '• **24/7** availability is an office policy matter — confirm with ' . self::companyLine() . ' before promising round-the-clock service.';
        }

        $lines[] = '';
        $lines[] = 'Check ' . assistantAdminLink('pages/appointments.php', 'Appointments') . ' for scheduled slots or ask _Show upcoming appointments_ in this assistant.';

        return implode("\n", $lines);
    }

    private static function stateFeesAnswer(): string
    {
        $us = self::isUsPractice();
        $payments = assistantAdminLink('pages/payments.php', 'Payments');

        $lines = [
            '**Standard notary fees**',
            '',
        ];

        if ($us) {
            $lines[] = 'In the **United States**, many states publish a **maximum fee schedule** for basic notarial acts (acknowledgment, jurat, oath, etc.). Fees above the maximum are not permitted for those acts.';
            $lines[] = '';
            $lines[] = '• Check your **state notary handbook** or Secretary of State website for current statutory amounts';
            $lines[] = '• **Travel, evenings, loan signings, and apostille coordination** are usually separate service fees';
            $lines[] = '• Always quote **your firm’s** fees before the appointment';
        } else {
            $lines[] = 'In the **UK**, notarial fees are **not set by a single national “state fee” schedule** like many US states. Fees reflect the **time, complexity, number of acts, travel, and legalisation** required.';
            $lines[] = '';
            $lines[] = '• Scrivener notaries and notaries public set fees in line with practice, regulation, and client agreement';
            $lines[] = '• Provide a **written estimate** before work begins (see your client engagement letter template)';
            $lines[] = '• **Apostille, embassy legalisation, and translation** costs are usually additional';
        }

        $lines[] = '';
        $lines[] = '**In this portal:** Review recent invoices and quotations in ' . $payments . ', or ask _List recent payments_ / _Show overdue invoices_ for firm billing context.';
        $lines[] = '';
        $lines[] = 'When clients ask about “standard state fees”, clarify whether they mean **statutory notary act fees** (US) or your **office fee schedule** (UK / general).';

        return implode("\n", $lines);
    }

    private static function documentPreparationAnswer(): string
    {
        $lines = [
            '**How to prepare a document before the appointment**',
            '',
            'Share this checklist with clients:',
            '',
            '1. **Leave signature blocks blank** unless your instructions say otherwise — many documents must be signed **in front of the notary**.',
            '2. **Bring valid photo ID** for every signer (and witnesses, if required).',
            '3. **Bring the complete document** including all pages and exhibits; read any covering letter from the foreign lawyer or bank.',
            '4. **Confirm notarial wording** — acknowledgment vs jurat vs certified copy; we can often attach a certificate if the document lacks one.',
            '5. **Witnesses** — if the document requires witnesses, arrange disinterested adults to attend with their own ID.',
            '6. **Translations** — if the document is not in English, check whether a certified translation is required for the destination country.',
            '7. **Legalisation** — ask whether an **apostille** or embassy step will be needed after notarization.',
            '8. **Corporate signers** — bring evidence of authority (board resolution, incumbency certificate) if signing for a company.',
            '9. **Payment** — confirm fees and payment method in advance.',
        ];

        $lines[] = '';
        $lines[] = 'Staff: record requirements in the case file (' . assistantAdminLink('pages/cases.php', 'Cases') . ') and attach the draft document before the appointment.';
        $lines[] = '';
        $lines[] = 'You can also upload a draft here and ask _summarize this document_ or _what is the amount?_ for a quick pre-visit review.';

        return implode("\n", $lines);
    }
}
