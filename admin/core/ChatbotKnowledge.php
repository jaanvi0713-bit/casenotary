<?php

declare(strict_types=1);

function chatbotIsSystemDataQuestion(string $message): bool
{
    return (bool) preg_match(
        '/\b(how many|list|show|count|dashboard|summary|snapshot|pending|clients?|cases?|payments?|invoices?|appointments?|notifications?|revenue|active cases?|upcoming|confirmed|scheduled|case-\d|total revenue|any pending|document|documents|doc|docs|upload|uploaded|file|files|receipt|quotation|proposal|overdue|activity|tell me if|any client|are there)\b/i',
        $message
    ) || chatbotWantsCount($message) || chatbotWantsList($message);
}

function chatbotIsGeneralQuestion(string $message): bool
{
    $normalized = strtolower(trim($message));

    if ($normalized === '' || chatbotIsSystemDataQuestion($normalized)) {
        return false;
    }

    if (preg_match('/case[- ]?\d{4}/i', $message)) {
        return false;
    }

    if (preg_match('/\b(how many|list clients|list cases|list payments|dashboard|pending invoice)\b/', $normalized)) {
        return false;
    }

    return (bool) preg_match(
        '/\b(what is|what are|what\'s|whats|explain|define|meaning of|how does|why do|why is|why are|difference between|compare|tips for|best practice|advice|help me understand|can you explain|describe|overview of|learn about|teach me|pros and cons|when should|who needs|do i need|tell me|give me|recommend|suggest|ideas for|ways to|should i|is it|are there|any tips|what happens|what about|talk about|general info|information about|write me|draft|compose|prepare|curious|thoughts on|opinion on|help with|guide me|walk me through)\b/',
        $normalized
    ) || (bool) preg_match('/^(what|why|how|who|when|where|explain|define|describe|is|are|can|should|tell|give|write|draft)\s+/i', $normalized);
}

function chatbotReplyForGeneralKnowledge(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (preg_match('/\bdifference between\b/i', $message)) {
        $compare = chatbotReplyForGeneralizedTemplate($message);
        if ($compare !== null) {
            return $compare;
        }
    }

    $topics = [
        '/\b(notary public|what is a notary|notaris|notarization|role of a notary)\b/' =>
            'A **notary public** is an official who verifies identity, witnesses signatures, administers oaths, and certifies copies of documents. '
            . 'They help prevent fraud by confirming signers appear willingly and understand what they are signing. '
            . 'In your portal, you track each matter as a **case**, schedule **appointments** for signings, and share **instructions** with clients.',

        '/\b(affidavit|what is an affidavit)\b/' =>
            'An **affidavit** is a written statement confirmed by oath or affirmation, used as evidence in legal proceedings. '
            . 'The signer swears the contents are true before a notary or commissioner for oaths. '
            . '**Tip:** Use clear **client instructions** on the case telling them to bring valid ID and arrive with the statement unsigned until the appointment.',

        '/\b(power of attorney|poa|lasting power|lpa)\b/' =>
            'A **Power of Attorney (POA)** lets someone act on another person\'s behalf in legal or financial matters. '
            . 'Requirements vary by jurisdiction — some need witnesses, others need registration. '
            . 'Confirm identity carefully, check capacity, and note any witnessing requirements on the **case** record.',

        '/\b(apostille|legalisation|legalization|hague convention)\b/' =>
            'An **apostille** certifies a document for use in countries that are party to the Hague Apostille Convention. '
            . 'Notaries often notarize first; apostilles are usually issued by a government office (e.g. FCDO in the UK). '
            . 'Ask clients where the document will be used and allow extra time for legalization.',

        '/\b(jurat|acknowledgment|acknowledgement|certificate)\b/' =>
            '• **Acknowledgment** — signer confirms they signed voluntarily; document may already be signed.\n'
            . '• **Jurat** — signer swears/affirms truthfulness, typically signing in the notary\'s presence.\n'
            . '• **Copy certification** — notary confirms a copy matches the original.\n'
            . 'Use the correct certificate wording for your jurisdiction.',

        '/\b(deed|trust deed|property deed|transfer deed)\b/' =>
            'A **deed** transfers or confirms an interest in property or rights. Deeds often require witnesses and sometimes land registry filing. '
            . 'Ensure clients bring all pages, related IDs, and any lender requirements. Track deadlines on the **case**.',

        '/\b(identity verification|verify identity|id check|kyc|know your customer)\b/' =>
            "**Identity verification best practices:**\n"
            . "1. Inspect **current, government-issued photo ID** (passport, driving licence).\n"
            . "2. Match name on ID to the document; note variations.\n"
            . "3. Record ID type and reference in your journal/case notes.\n"
            . "4. Be alert to coercion or confusion — refuse service if identity is doubtful.",

        '/\b(remote online notar|ron|video notar|online notar)\b/' =>
            '**Remote Online Notarization (RON)** uses audio-video technology to notarize when signer and notary are not physically together, where law allows. '
            . 'Rules differ by state/country — credentialing, technology standards, and record-keeping apply. '
            . 'Your portal supports **appointments** and **client instructions** for both in-person and remote workflows.',

        '/\b(journal|notary journal|record keeping|audit trail)\b/' =>
            'A **notary journal** (where required) logs each notarial act: date, type, document, signer, ID, fees. '
            . 'Your portal\'s **case activity**, **documents**, and **appointments** supplement formal records. '
            . 'Keep entries contemporaneous and retain per local law.',

        '/\b(conflict of interest|impartial|refuse service)\b/' =>
            'Notaries must remain **impartial** — do not notarize if you benefit personally, are a party to the document, or have a close relationship that creates bias. '
            . 'When in doubt, decline and refer the client elsewhere. Document the reason in the **case notes**.',

        '/\b(client communication|follow up|customer service|professional email)\b/' =>
            '**Client communication tips:**\n'
            . '• Send **client instructions** early via the portal.\n'
            . '• Confirm appointments 24 hours ahead.\n'
            . '• Use plain language — avoid jargon.\n'
            . '• Email **quotations** and **client letters** from the case page.\n'
            . '• Set case status to **Waiting for Client** when you need documents back.',

        '/\b(tough client|difficult client|angry client|upset client|handle a client|handle an? tough|deal with.{0,12}client|problem client|challenging client|complaint|unhappy client|hostile client|rude client)\b/' =>
            "**Handling a difficult client:**\n\n"
            . "1. **Stay calm & professional** — don't argue; listen first.\n"
            . "2. **Clarify the issue** — repeat their concern in your own words so they feel heard.\n"
            . "3. **Stick to facts** — refer to **case notes**, **instructions**, and agreed fees.\n"
            . "4. **Set boundaries** — explain what you can and cannot do as a notary.\n"
            . "5. **Document everything** — update the **case status** and add notes after the interaction.\n"
            . "6. **Follow up in writing** — use a **client letter** or email summarising next steps.\n"
            . "7. **Know when to pause** — if a signer lacks capacity or is under duress, **decline to notarize**.\n\n"
            . "_If this is about a **specific client** in your system, ask **details of [name]** for their cases and invoices._",

        '/\b(marketing|grow|attract clients|business development|get more clients)\b/' =>
            '**Growing a notary practice:**\n'
            . '• Partner with solicitors, estate agents, and lenders.\n'
            . '• Offer clear pricing via **quotations** in the portal.\n'
            . '• Collect reviews and respond promptly to **appointment requests**.\n'
            . '• Use **Settings** branding so clients recognise your office.\n'
            . '• Follow up on **pending invoices** professionally.',

        '/\b(pricing|fees|how much to charge|fee schedule)\b/' =>
            'Notary fees may be **regulated** (statutory) or **market-based** depending on service and location. '
            . 'Common approach: separate fees for acknowledgments, jurats, travel, and after-hours work. '
            . 'Use **Cases → fees** and **Generate Quotation** so clients see costs upfront.',

        '/\b(gdpr|data protection|privacy|personal data)\b/' =>
            '**Data protection (GDPR / UK GDPR):** collect only necessary client data, secure it, and explain how it is used. '
            . 'Your portal stores client profiles, cases, and documents — restrict admin access, use strong passwords, and configure **SMTP** securely in **Settings**.',

        '/\b(stripe|online payment|card payment|pay online)\b/' =>
            'Clients can pay **invoices** through the portal when **Stripe** is configured. '
            . 'Admins create invoices from cases; payments appear under **Payments**. '
            . 'Ask **“total revenue”** or **“any pending payments”** for live figures.',

        '/\b(deadline|turnaround|how long|processing time)\b/' =>
            'Set realistic **deadlines** on each **case** and communicate them in **client instructions**. '
            . 'Complex legalization or apostille work often needs extra days. '
            . 'Use **Appointments** to book signing slots and **Notifications** to alert clients.',

        '/\b(witness|witnessing|two witnesses)\b/' =>
            'Some documents require **witnesses** in addition to notarization (e.g. wills, deeds). '
            . 'Witnesses must usually be independent adults who are present when the signer signs. '
            . 'Clarify requirements before the appointment and note them in **client instructions**.',

        '/\b(oath|affirmation|swear|solemnly)\b/' =>
            'An **oath** is a religious pledge; an **affirmation** is a non-religious equivalent — both carry legal weight. '
            . 'Ask the signer which they prefer before administering a **jurat** or **affidavit**.',

        '/\b(mobile notary|travel fee|visit client|on site)\b/' =>
            '**Mobile notaries** travel to clients — factor in mileage, time, and urgency when quoting. '
            . 'Schedule via **Appointments**, include travel in the **case fee**, and send the address in **client instructions**.',

        '/\b(translation|foreign language|interpreter|bilingual)\b/' =>
            'If a document or signer uses another language, you may need a **qualified interpreter** and/or a certified translation. '
            . 'Do not notarize if you cannot verify the signer understands the document.',

        '/\b(statutory declaration|stat dec)\b/' =>
            'A **statutory declaration** is a formal written statement declared to be true, similar to an affidavit but often used in administrative contexts. '
            . 'Rules differ from affidavits — confirm the correct form for your jurisdiction.',

        '/\b(limited company|company formation|certificate of incorporation)\b/' =>
            'Companies House and similar filings often need **certified copies** or **notarized** documents for foreign use. '
            . 'Confirm which pages the receiving authority needs and allow time for **apostille** if required.',

        '/\b(openai key|api key|don\'t have a key|no api key|get a key|need a key|without a key|without installing|no install|install anything)\b/' =>
            "**Open-ended answers without installing anything or using an API key:**\n\n"
            . "This assistant already supports that. Just ask naturally:\n\n"
            . "• **Explain** — *what is an apostille?*, *explain power of attorney*\n"
            . "• **Advice** — *tips for client communication*, *how to grow my notary business*\n"
            . "• **Drafts** — *draft an email to a client*, *write a follow-up letter*\n"
            . "• **Compare** — *difference between acknowledgment and jurat*\n"
            . "• **Your data** — *details of Emily*, *what about her invoices*\n\n"
            . "I use a **built-in knowledge library** plus **live database** answers — no ChatGPT, Ollama, or cloud key required.",

        '/\b(ai|artificial intelligence|chatgpt|openai)\b/' =>
            'This assistant uses your **live business data** and built-in **notary knowledge** — no external AI service is needed. '
            . 'Ask about **clients, cases, payments, appointments**, portal workflows, or topics like **“what is an affidavit?”**',

        '/\b(hello|hi there|good morning|good afternoon)\b/' =>
            'Hello! I can help with **live data** (clients, cases, payments, appointments), **portal how-tos**, and **general notary knowledge**. '
            . 'Try **“dashboard summary”**, **“what is an affidavit?”**, or **“how do I add client instructions?”**',
    ];

    foreach ($topics as $pattern => $answer) {
        if (preg_match($pattern, $normalized)) {
            return $answer;
        }
    }

    $expanded = chatbotReplyForExpandedKnowledge($message);
    if ($expanded !== null) {
        return $expanded;
    }

    if (!chatbotIsGeneralQuestion($message)) {
        return null;
    }

    $template = chatbotReplyForGeneralizedTemplate($message);
    if ($template !== null) {
        return $template;
    }

    // Broad "how does X work" without portal context
    if (preg_match('/\bhow does\b/', $normalized) && !preg_match('/\b(portal|system|this app|software|platform)\b/', $normalized)) {
        return chatbotTemplateHowDoes($message);
    }

    return null;
}

function chatbotReplyForExpandedKnowledge(string $message): ?string
{
    $normalized = strtolower(trim($message));

    $topics = [
        '/\b(will|last will|testament|probate|estate planning|executor)\b/' =>
            '**Wills & estates:** A will directs asset distribution after death; probate is the court process validating it. '
            . 'Notaries often witness signatures or certify copies — but **will execution rules vary** (witnesses, capacity, formatting). '
            . 'Do not advise on legal content; ensure proper witnessing and refer complex matters to a solicitor.',

        '/\b(contract|agreement|terms and conditions|binding agreement)\b/' =>
            'A **contract** is an agreement with offer, acceptance, and consideration. Notaries typically **witness signatures**, not validate commercial terms. '
            . 'Confirm identity and willingness; note if signers had opportunity to read the document.',

        '/\b(immigration|visa|i-9|work permit|passport copy)\b/' =>
            '**Immigration-related documents** often need notarized copies or sworn statements. Requirements depend on the **destination country and form**. '
            . 'Verify exact pages needed, keep copies on the **case**, and allow extra time for **apostille** if sending abroad.',

        '/\b(real estate|property|conveyancing|land registry|title deed|mortgage)\b/' =>
            '**Property transactions** may need notarized deeds, ID verification, or certified copies for lenders and registries. '
            . 'Coordinate with solicitors and title companies; track deadlines on the **case** and confirm which pages must be notarized.',

        '/\b(certified copy|true copy|copy certification)\b/' =>
            'A **certified copy** confirms a copy matches the original. Compare page-by-page, use your jurisdiction\'s certificate wording, and record the act in your **journal/case notes**. '
            . 'Never certify a document you cannot verify as original.',

        '/\b(credible witness|subscribing witness|two witness)\b/' =>
            'A **credible witness** may identify a signer when ID is unavailable (where law permits). The witness must be impartial, know the signer, and often must have valid ID themselves. Check local rules before proceeding.',

        '/\b(expired id|expired passport|no id|forgot id)\b/' =>
            '**Expired ID** is usually not acceptable. Options: reschedule with valid ID, use **credible witnesses** if permitted, or refuse service. Document your decision on the **case**.',

        '/\b(capacity|mental capacity|competent|understand the document|duress|coercion)\b/' =>
            'Assess whether the signer **understands** the document and acts **freely**. Signs of confusion, pressure, or incapacity mean **decline to notarize** and note observations (without diagnosing). Refer to legal counsel when appropriate.',

        '/\b(elder|senior|vulnerable adult|elder abuse)\b/' =>
            'Watch for **undue influence** with elderly clients — unfamiliar helpers speaking for them, urgency, or isolation. Interview the signer alone when safe. Report suspected abuse per local law.',

        '/\b(corporate|company|llc|ltd|inc|board resolution|shareholder|director sign)\b/' =>
            '**Corporate notarizations** require verifying **signing authority** (resolution, articles, or secretary certificate). Confirm the signer\'s role and that the entity name matches records.',

        '/\b(trust|trustee|beneficiary|living trust)\b/' =>
            '**Trust documents** involve trustees and beneficiaries; notarial acts vary by document type. Confirm which pages need notarization and whether witnesses are required separately from notarization.',

        '/\b(medical power|healthcare proxy|living will|advance directive)\b/' =>
            '**Healthcare directives** authorize medical decisions. Witnessing and notarization rules differ by jurisdiction — use the official form for your state/country and follow witnessing instructions exactly.',

        '/\b(loan|promissory note|financial affidavit|bank form)\b/' =>
            '**Financial documents** for lenders often need notarized signatures or affidavits. Match name to ID exactly; clients should read all pages before signing in your presence.',

        '/\b(late payment|overdue invoice|payment reminder|chase payment|collect debt)\b/' =>
            "**Collecting overdue payments:**\n"
            . "1. Send a polite **reminder** from **Payments** or email.\n"
            . "2. Reference the **invoice number** and due date.\n"
            . "3. Offer a payment link if **Stripe** is enabled.\n"
            . "4. Escalate professionally after agreed terms — avoid aggressive language.",

        '/\b(refund|cancellation policy|cancel appointment|no show)\b/' =>
            'Publish clear **cancellation and refund policies** in **Settings** or client letters. For no-shows, document on the **case**; charge fees only if disclosed upfront. Reschedule via **Appointments**.',

        '/\b(professional liability|errors and omissions|e&o|indemnity insurance)\b/' =>
            '**Professional indemnity / E&O insurance** protects against negligence claims. Maintain coverage appropriate for notarial volume, keep certificates on file, and follow insurer guidelines for record-keeping.',

        '/\b(cybersecurity|password|phishing|backup|data breach|secure email)\b/' =>
            "**Security basics:**\n"
            . "• Strong unique passwords for admin accounts\n"
            . "• Configure **SMTP/TLS** in Settings\n"
            . "• Back up database and uploads regularly\n"
            . "• Never share login credentials; beware phishing emails",

        '/\b(vat|tax|accounting|bookkeeping|expense|profit margin)\b/' =>
            'Keep clear **invoice and payment records** in the portal for accounting. Consult a qualified accountant for **VAT/tax** rules — notary fees may have specific reporting requirements in your jurisdiction.',

        '/\b(cash flow|financial health|business finances|revenue goal)\b/' =>
            'Monitor **total revenue**, **pending invoices**, and appointment volume from the **dashboard**. Reduce cash-flow gaps by sending quotations early and following up on overdue payments.',

        '/\b(hire|staff|employee|delegate|virtual assistant|outsource)\b/' =>
            '**Delegating work:** Admins handle portal operations; only authorized **notaries** perform notarial acts. Train staff on client privacy, appointment scheduling, and never notarizing without proper commission.',

        '/\b(time management|productivity|busy|overwhelmed|prioriti)\b/' =>
            "**Productivity tips:**\n"
            . "• Block **appointments** with buffer time\n"
            . "• Use **case status** (Waiting for Client) to track bottlenecks\n"
            . "• Batch email and invoicing tasks\n"
            . "• Review **dashboard** each morning for priorities",

        '/\b(stress|burnout|work life balance|overwork)\b/' =>
            'Running a practice is demanding. Set **business hours** in Settings, avoid overbooking, and use appointment buffers. Take breaks between mobile visits and delegate admin tasks where possible.',

        '/\b(referral|word of mouth|networking|partnership|solicitor referral)\b/' =>
            'Build **referral relationships** with solicitors, estate agents, and accountants. Deliver reliable turnaround, clear **quotations**, and professional follow-up — partners refer clients who get a smooth experience.',

        '/\b(review|google review|testimonial|reputation|online presence)\b/' =>
            'After completed cases, politely ask satisfied clients for **reviews**. Keep **Settings** branding professional; respond to feedback promptly. Never incentivize fake reviews.',

        '/\b(social media|facebook|linkedin|instagram|marketing online)\b/' =>
            'Share practical tips (ID requirements, appointment prep) on **social media**. Link to your client portal for bookings. Avoid giving jurisdiction-specific legal advice in posts.',

        '/\b(website|seo|google business|find my business online)\b/' =>
            'A clear website with services, hours, and contact info helps discovery. Keep **Google Business Profile** updated with hours matching **Settings**. Use consistent branding.',

        '/\b(complaint|unhappy client|unhappy clients|dissatisfied|dispute|conflict resolution|angry customer)\b/' =>
            "**Handling complaints:**\n"
            . "1. Listen and acknowledge without arguing\n"
            . "2. Review the **case** record and communications\n"
            . "3. Offer a factual timeline and next step\n"
            . "4. Escalate to a principal if legal risk exists\n"
            . "5. Document the resolution in **case notes**",

        '/\b(negotiat|discount|reduce fee|price match|waive fee)\b/' =>
            'Fees can be adjusted before issuing a **quotation**. Document agreed fees on the **case**; avoid retroactive discounts without clarity. Statutory fees may be non-negotiable.',

        '/\b(email template|write an email|professional email|follow up email|thank you email)\b/' =>
            "**Professional email structure:**\n"
            . "• Clear subject line with **case number** if applicable\n"
            . "• Brief greeting and purpose\n"
            . "• Bullet next steps or attachments needed\n"
            . "• Friendly closing with office contact from **Settings**",

        '/\b(onboard|welcome new client|new client process|intake)\b/' =>
            "**Client onboarding:**\n"
            . "1. **Add Client** with accurate contact details\n"
            . "2. Send **portal login** credentials\n"
            . "3. Create **case** with fees and **instructions**\n"
            . "4. Email **quotation/client letter**\n"
            . "5. Schedule **appointment** if needed",

        '/\b(close case|complete case|archive|case closed|finish matter)\b/' =>
            'When work is done, set case status to **Completed** or **Closed**, ensure **invoices** are paid, upload final documents, and send a closing message. Archived cases remain searchable for compliance.',

        '/\b(zoom|teams|video call|remote meeting|virtual meeting)\b/' =>
            'For **remote meetings**, confirm whether **RON** is permitted in your jurisdiction. Otherwise use video for consultation only and notarize in person when required. Send the link in **appointment** notes.',

        '/\b(office hours|holiday|out of office|closed|vacation)\b/' =>
            'Set **business hours** in **Settings** and communicate holidays via client portal **notifications** or email autoresponder. Update **appointment** availability accordingly.',

        '/\b(notary seal|stamp|embosser|commission|renew commission|notary bond)\b/' =>
            'Maintain a valid **notary commission**, **seal/stamp**, and **bond** where required. Renew before expiry; incorrect or expired commission can invalidate acts.',

        '/\b(continuing education|cpe|training|professional development)\b/' =>
            'Many jurisdictions require **continuing education** for notaries. Keep certificates, stay current on law changes, and apply new rules to your **client instructions** and procedures.',

        '/\b(fraud|fake id|forgery|suspicious|red flag|scam)\b/' =>
            "**Fraud red flags:**\n"
            . "• ID photo or description mismatch\n"
            . "• Third party insisting on answers for signer\n"
            . "• Unusual urgency or large cash deals\n"
            . "• Documents in wrong language without interpreter\n"
            . "Refuse service and document concerns if in doubt.",

        '/\b(interpreter|sign language|deaf|hard of hearing|accessibility)\b/' =>
            'Provide **reasonable access** — qualified interpreters for non-English or deaf signers when needed. The signer must understand the document; do not rely on family members with a conflict of interest.',

        '/\b(minor|under 18|child sign|parental consent)\b/' =>
            'Minors generally **cannot** enter binding contracts; guardians sign on their behalf where permitted. Verify authority and age; special rules apply for passports, travel consent, and school forms.',

        '/\b(apostille country|embassy|consulate|foreign use|international document)\b/' =>
            'For **international use**: notarize → **apostille** (Hague countries) or **embassy legalization** (others). Confirm receiving country rules early — chain and order matter.',

        '/\b(laminate|scan|digitize|electronic document|e-sign|docusign)\b/' =>
            'Prefer **original documents** for notarization. Scanned copies may suffice for some administrative uses but not for all legal acts. **E-signatures** follow separate rules from in-person notarization.',

        '/\b(journal entry|record keeping|retention|how long keep records)\b/' =>
            'Retain **journal entries**, **case files**, and **ID copies** per local law (often years). Your portal **cases**, **documents**, and **activity** support audits — export or back up regularly.',

        '/\b(what can you do|what do you do|your capabilities|help me with anything|what questions)\b/' =>
            "**I can help with a wide range of topics:**\n\n"
            . "**Live data** — clients, cases, payments, appointments, dashboard\n"
            . "**Portal how-tos** — workflows, settings, documents, invoicing\n"
            . "**Notary & legal documents** — affidavits, POA, wills, corporate, immigration\n"
            . "**Business advice** — marketing, client service, payments, productivity, security\n\n"
            . "Ask naturally — e.g. *“tips for late payments”*, *“what is an apostille?”*, or *“how do I close a case?”*",

        '/\b(capital|capitalize|grammar|spelling|proofread|wording)\b/' =>
            'For **document wording**, notaries do not draft legal content unless qualified. Proofread certificates and notarial wording for accuracy; refer clients to solicitors for substantive legal text.',

        '/\b(weather|sports|news|recipe|movie|music|joke)\b/' =>
            'I focus on **your notary business and this portal** rather than general trivia. '
            . 'Ask me about **cases, clients, appointments**, or **notary/document topics** — or say **“dashboard summary”** for a business snapshot.',
    ];

    foreach ($topics as $pattern => $answer) {
        if (preg_match($pattern, $normalized)) {
            return $answer;
        }
    }

    return null;
}

function chatbotExtractQuestionSubject(string $message): string
{
    $subject = trim($message);
    $subject = preg_replace(
        '/^(please\s+)?(can you|could you|would you|tell me|explain|describe|define)\s+/i',
        '',
        $subject
    ) ?? $subject;
    $subject = preg_replace(
        '/^(what is|what are|what\'s|whats|how to|how do i|how can i|how should i|why is|why are|why do|why does|when should|who needs|give me|any tips for|tips for|advice on|advice about|information about|info on|learn about)\s+/i',
        '',
        $subject
    ) ?? $subject;
    $subject = rtrim($subject, '? .!');
    $subject = preg_replace('/\s+(in general|for me|please)$/i', '', $subject) ?? $subject;

    return trim($subject) !== '' ? trim($subject) : 'your question';
}

function chatbotReplyForGeneralizedTemplate(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (chatbotIsSystemDataQuestion($message) && !chatbotIsGeneralQuestion($message)) {
        return null;
    }

    if (preg_match('/\bdifference between\b(.+)\band\b(.+)/i', $message, $matches)) {
        return chatbotTemplateCompare(trim($matches[1]), trim($matches[2]));
    }

    $subject = chatbotExtractQuestionSubject($message);

    if (preg_match('/\b(what is|what are|what\'s|whats|define|meaning of)\b/', $normalized)) {
        return chatbotTemplateWhatIs($subject);
    }

    if (preg_match('/\b(tips|advice|best practice|recommend|suggest|ideas|ways to improve)\b/', $normalized)) {
        $expanded = chatbotReplyForExpandedKnowledge($message);
        if ($expanded !== null) {
            return $expanded;
        }

        return chatbotTemplateTips($subject);
    }

    if (preg_match('/\b(how to|how do i|how can i|how should i|steps to|way to)\b/', $normalized)) {
        $expanded = chatbotReplyForExpandedKnowledge($message);
        if ($expanded !== null) {
            return $expanded;
        }

        return chatbotTemplateHowTo($subject, $message);
    }

    if (preg_match('/\b(why|why is|why are|why do|why does|why should)\b/', $normalized)) {
        return chatbotTemplateWhy($subject);
    }

    if (preg_match('/\bhow does\b/', $normalized)) {
        return chatbotTemplateHowDoes($message);
    }

    if (preg_match('/\?/', $message) || preg_match('/^(what|why|how|who|when|where|can|should|is|are|do|does)\b/i', $normalized)) {
        return chatbotTemplateOpenAnswer($subject, $message);
    }

    return null;
}

function chatbotTemplateWhatIs(string $subject): string
{
    return "**About \"{$subject}\":**\n\n"
        . "Here is a practical overview:\n\n"
        . "• **Purpose** — Clarify what role this plays in legal, business, or client workflows.\n"
        . "• **Your practice** — Note requirements on the relevant **case**, verify **ID**, and follow jurisdiction rules.\n"
        . "• **Client communication** — Set expectations in **client instructions** and confirm documents before the **appointment**.\n\n"
        . "_For notary-specific terms (affidavit, apostille, POA, etc.), ask directly — e.g. **“what is an affidavit?”**_\n\n"
        . "If this relates to a **specific client or case**, name them first for tailored next steps.";
}

function chatbotTemplateHowTo(string $subject, string $message): string
{
    if (preg_match('/\b(portal|system|software|app|platform|this system)\b/i', $message)) {
        return "**How to {$subject} in this portal:**\n\n"
            . "1. Check the sidebar — **Clients**, **Cases**, **Payments**, **Appointments**, or **Settings**.\n"
            . "2. Open the relevant record and use **Edit**, **Generate**, or **Schedule** actions.\n"
            . "3. Email or notify the client from the **case page** when documents or instructions are ready.\n\n"
            . "Try a specific question like **“how do I create a case?”** or **“how to schedule an appointment?”**";
    }

    return "**How to approach \"{$subject}\":**\n\n"
        . "1. **Define the goal** — What outcome does the client or your office need?\n"
        . "2. **Check requirements** — Law, lender, embassy, or receiving authority rules.\n"
        . "3. **Gather documents & ID** — List items in **client instructions**.\n"
        . "4. **Execute & record** — Appointment, notarial act, **case notes**, and invoicing.\n"
        . "5. **Follow up** — Status updates, apostille, or delivery as needed.\n\n"
        . "For portal-specific steps, include words like **“in the portal”** or ask **help**.";
}

function chatbotTemplateWhy(string $subject): string
{
    return "**Why \"{$subject}\" matters:**\n\n"
        . "In a notary practice, clarity on this helps you **manage risk**, **serve clients properly**, and **keep records defensible**.\n\n"
        . "• Protects against fraud and disputes\n"
        . "• Sets client expectations early\n"
        . "• Supports compliance and professional standards\n\n"
        . "Apply the principle on each **case** and document your reasoning in **notes** when decisions are non-routine.";
}

function chatbotTemplateTips(string $subject): string
{
    return "**Tips for {$subject}:**\n\n"
        . "• **Be proactive** — Communicate early via portal **instructions** and **notifications**.\n"
        . "• **Be consistent** — Use the same process for every similar matter.\n"
        . "• **Be documented** — Case status, notes, and uploaded files create an audit trail.\n"
        . "• **Be professional** — Clear fees (**quotations**), polite follow-ups, and realistic deadlines.\n"
        . "• **Be cautious** — When unsure, pause and verify with official guidance or counsel.\n\n"
        . "Ask **dashboard summary** to see where your business stands today.";
}

function chatbotTemplateCompare(string $left, string $right): string
{
    $left  = rtrim($left, '?');
    $right = rtrim($right, '?');

    return "**{$left} vs {$right}:**\n\n"
        . "These terms are often confused. In general:\n\n"
        . "• **{$left}** — Often used in one context (formal legal act, document type, or process).\n"
        . "• **{$right}** — May differ in who signs, where it is filed, or legal effect.\n\n"
        . "Requirements **vary by jurisdiction**. Confirm the receiving authority\'s rules, use the correct certificate wording, and record details on the **case**.\n\n"
        . "Ask about either term directly — e.g. **“what is an affidavit?”** or **“what is an apostille?”**";
}

function chatbotTemplateHowDoes(string $message): string
{
    $subject = chatbotExtractQuestionSubject($message);

    return "**How {$subject} works (general):**\n\n"
        . "Processes depend on jurisdiction and document type. A reliable approach:\n\n"
        . "1. Identify the **document** and **receiving party** (court, bank, embassy, etc.).\n"
        . "2. Confirm **signing, witnessing, and notarization** requirements.\n"
        . "3. Schedule an **appointment**; verify **identity** and willingness.\n"
        . "4. Complete the act; retain **journal/case** records.\n"
        . "5. Add **apostille** or further legalization if sending abroad.\n\n"
        . "For **this portal**, ask **“how does the client portal work?”** or a specific **how do I…** question.";
}

function chatbotTemplateOpenAnswer(string $subject, string $message): string
{
    $company = getCompanySettings();
    $brand   = $company['company_name'] ?? 'your office';
    $stats   = getDashboardStats();

    $lines = [
        "**Regarding {$subject}**",
        '',
        "Here is a thoughtful overview for **{$brand}** — no external AI or install needed:",
        '',
    ];

    if (preg_match('/\b(client|customer|service|communication)\b/i', $message)) {
        $lines[] = '**Client perspective:** Clear instructions, realistic timelines, and professional follow-up build trust. '
            . 'Use **client instructions** on each case and keep status updated so clients see progress in the portal.';
        $lines[] = '';
    }

    if (preg_match('/\b(money|fee|price|charge|profit|business)\b/i', $message)) {
        $lines[] = '**Business perspective:** You have **' . formatCurrency((float) $stats['total_revenue']) . '** total revenue '
            . 'and **' . (int) $stats['pending_invoices'] . '** pending invoice(s). '
            . 'Transparent **quotations** and timely invoicing protect cash flow.';
        $lines[] = '';
    }

    $lines[] = '**Practical steps you can take now:**';
    $lines[] = '1. Clarify whether this is about **your live data**, a **portal task**, or **notary/document knowledge**.';
    $lines[] = '2. For **numbers** — try *dashboard summary*, *list clients*, or *overdue invoices*.';
    $lines[] = '3. For **how-to** — ask *how do I create a case?* or *how to schedule an appointment?*';
    $lines[] = '4. For **notary topics** — ask *what is an affidavit?*, *apostille*, or *power of attorney*.';
    $lines[] = '5. For a **specific person** — ask *details of [client name]* then follow up with *what about her invoices*.';
    $lines[] = '';
    $lines[] = 'Ask a follow-up with more detail — I remember our conversation and will build on earlier answers.';

    return implode("\n", $lines);
}

function chatbotTemplateDraftContent(string $message): string
{
    $company = getCompanySettings();
    $brand   = companyBrandName($company);
    $subject = chatbotExtractQuestionSubject($message);

    if (preg_match('/\b(email|e-mail)\b/i', $message)) {
        return "**Draft email template** (edit before sending):\n\n"
            . "**Subject:** Regarding your notary appointment — {$brand}\n\n"
            . "Dear [Client name],\n\n"
            . "Thank you for choosing **{$brand}**. Regarding [topic], please note:\n\n"
            . "• [Key point 1 — e.g. documents to bring]\n"
            . "• [Key point 2 — e.g. appointment date/time]\n"
            . "• [Key point 3 — e.g. fee or next steps]\n\n"
            . "If you have questions, reply to this email or use the client portal.\n\n"
            . "Kind regards,\n[Your name]\n{$brand}\n\n"
            . "_Tip: Copy this into your mail client, or use **Client Letter** on the case page to send from the portal._";
    }

    if (preg_match('/\b(letter|message|reply|response)\b/i', $message)) {
        return "**Draft client letter** (customise for your case):\n\n"
            . "[Date]\n\n"
            . "Dear [Client name],\n\n"
            . "Re: [Case / matter reference]\n\n"
            . "We write regarding {$subject}. [Explain situation in plain language.]\n\n"
            . "Please [action required — e.g. attend appointment / provide documents / review quotation].\n\n"
            . "Yours sincerely,\n**{$brand}**\n\n"
            . "_Generate a formal PDF from **Cases → Client Letter** when ready to send._";
    }

    return chatbotTemplateTips($subject);
}

/**
 * Rich open-ended replies using built-in knowledge only — no API key or local install.
 */
function chatbotReplyForOpenEndedLocal(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if ($normalized === '' || preg_match('/^(thanks|thank you|bye|ok)$/i', $normalized)) {
        return null;
    }

    if (preg_match('/\b(write|draft|compose|prepare)\b.*\b(email|letter|message|template|reply|response)\b/i', $message)) {
        return chatbotTemplateDraftContent($message);
    }

    $general = chatbotReplyForGeneralKnowledge($message);
    if ($general !== null) {
        return $general;
    }

    if (chatbotIsGeneralQuestion($message) || preg_match('/\?/', $message)) {
        $fused = chatbotFuseKnowledgeByKeywords($message);
        if ($fused !== null) {
            return $fused;
        }

        $template = chatbotReplyForGeneralizedTemplate($message);
        if ($template !== null) {
            return $template;
        }
    }

    return null;
}

function chatbotFuseKnowledgeByKeywords(string $message): ?string
{
    $normalized = strtolower(trim($message));
    $words = preg_split('/\s+/', preg_replace('/[^\w\s]/', ' ', $normalized)) ?: [];
    $stop = array_flip(chatbotLookupStopWords());
    $candidates = [];

    foreach ($words as $word) {
        if (strlen($word) < 4 || isset($stop[$word])) {
            continue;
        }
        $snippet = chatbotReplyForGeneralKnowledge($word);
        if ($snippet !== null && !in_array($snippet, $candidates, true)) {
            $candidates[] = $snippet;
        }
        if (count($candidates) >= 2) {
            break;
        }
    }

    if ($candidates === []) {
        $expanded = chatbotReplyForExpandedKnowledge($message);
        if ($expanded !== null) {
            return $expanded;
        }

        return null;
    }

    if (count($candidates) === 1) {
        return $candidates[0];
    }

    return "**Here's what I can share on that topic:**\n\n"
        . implode("\n\n---\n\n", $candidates)
        . "\n\n_Ask about a specific client or case name for tailored data from your system._";
}

function getChatbotSystemSnapshot(): array
{
    $stats  = getDashboardStats();
    $userId = Auth::id();

    return [
        'clients'              => (int) $stats['total_clients'],
        'active_cases'         => (int) $stats['active_cases'],
        'pending_invoices'     => (int) $stats['pending_invoices'],
        'upcoming_appointments'=> (int) $stats['upcoming_appointments'],
        'total_revenue'        => (float) $stats['total_revenue'],
    ];
}
