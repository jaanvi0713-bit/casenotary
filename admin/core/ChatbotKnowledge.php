<?php

declare(strict_types=1);

function chatbotLookupStopWords(): array
{
    // Lightweight English stop-word list used for local “keyword fusion” replies.
    return [
        'a','an','the','and','or','but','if','then','else','when','where','why','how','what','which','who','whom',
        'to','of','in','on','at','by','for','with','from','into','onto','over','under','above','below',
        'is','are','was','were','be','been','being','am','do','does','did','done','have','has','had',
        'can','could','should','would','may','might','will','shall',
        'i','you','he','she','it','we','they','me','my','your','yours','our','ours','their','theirs','his','her',
        'this','that','these','those','there','here','then','than',
        'as','so','such','just','also','too','very','more','most','less',
        'any','all','some','no','not','yes',
        'please','help','about','regarding','concerning',
        'draft','compose','prepare','write','email','letter','document','template','reply','response',
    ];
}

function chatbotIsSystemDataQuestion(string $message): bool
{
    return (bool) preg_match(
        '/\b(how many|list|show|count|dashboard|summary|snapshot|pending|clients?|cases?|payments?|invoices?|appointments?|notifications?|revenue|active cases?|upcoming|confirmed|scheduled|case-\d|total revenue|any pending|document|documents|doc|docs|upload|uploaded|file|files|receipt|quotation|proposal|overdue|activity|tell me if|any client|are there)\b/i',
        $message
    ) || chatbotWantsCount($message) || chatbotWantsList($message);
}

function chatbotIsGeneralQuestion(string $message): bool
{
    return (bool) preg_match(
        '/\b(what|why|how|when|where|who|can|could|should|would|does|do|is|are)\b/i',
        $message
    ) || str_contains(trim($message), '?');
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
    $focused = chatbotReplyForFocusedQuestion($message);
    if ($focused !== null) {
        return $focused;
    }

    $normalized = strtolower(trim($message));

    if (chatbotIsSystemDataQuestion($message)) {
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
        $portal = chatbotReplyForFocusedQuestion($message);
        if ($portal !== null) {
            return $portal;
        }

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
    $known = chatbotReplyForFlexibleKnowledge($subject);
    if ($known !== null) {
        return $known;
    }

    return "**{$subject}:** A term used in legal or notarial work — exact meaning depends on **jurisdiction** and **document type**. "
        . 'Name a **specific term** (e.g. *apostille*, *affidavit*) for a precise definition.';
}

function chatbotTemplateHowTo(string $subject, string $message): string
{
    $focused = chatbotReplyForFocusedQuestion($message);
    if ($focused !== null) {
        return $focused;
    }

    if (preg_match('/\b(portal|system|software|app|platform|this system)\b/i', $message)) {
        return 'Rephrase with the exact task — e.g. *How do I create a case?* or *Where are settings?* — and I will give step-by-step instructions only for that.';
    }

    return "**How to handle {$subject}:** Confirm requirements with the receiving authority, gather documents and ID, complete the notarial act, and record it on the **case**.";
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
    $focused = chatbotReplyForFocusedQuestion($message);
    if ($focused !== null) {
        return $focused;
    }

    if (chatbotIsPortalSystemQuestion($message) || chatbotIsProceduralQuery($message)) {
        $portal = chatbotReplyForPortalSystemQuestion($message);
        if ($portal !== null) {
            return $portal;
        }
    }

    return 'I need a bit more specificity to answer that directly. '
        . 'Try one clear question — e.g. *How do I create a case?*, *What is an apostille?*, or *List overdue invoices*.';
}

function chatbotIsDraftRequest(string $message): bool
{
    $normalized = strtolower(trim($message));

    if ($normalized === '' || chatbotIsSystemDataQuestion($message)) {
        return false;
    }

    if (!preg_match('/\b(draft|write|compose|prepare|create|generate|help me write|help me draft)\b/i', $message)) {
        return false;
    }

    if (preg_match(
        '/\b(email|e-mail|letter|message|memo|memorandum|document|doc|contract|agreement|affidavit|'
        . 'quotation|quote|invoice|receipt|policy|procedure|checklist|certificate|attestation|nda|'
        . 'will|poa|power of attorney|template|notice|reminder|confirmation|instructions|summary|'
        . 'report|proposal|statement|sworn|journal|cover letter|client letter|follow.?up|demand|'
        . 'acknowledgment|acknowledgement|apostille|deed|minutes|script|terms)\b/i',
        $message
    )) {
        return true;
    }

    return (bool) preg_match('/\b(draft|write|compose|prepare)\s+(me\s+)?(a|an|the)\b/i', $message);
}

function chatbotExpandShortForms(string $message): string
{
    $normalized = strtolower(trim($message));
    $replacements = [
        '/\bpoa\b/'           => 'power of attorney',
        '/\blpa\b/'           => 'lasting power of attorney',
        '/\bnda\b/'           => 'non disclosure agreement',
        '/\bappt\b/'          => 'appointment',
        '/\bappts\b/'         => 'appointments',
        '/\bauth\b/'          => 'authentication',
        '/\blegalisation\b/'  => 'legalization apostille',
        '/\blegalization\b/'  => 'legalization apostille',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $normalized = preg_replace($pattern, $replacement, $normalized) ?? $normalized;
    }

    return trim($normalized);
}

/**
 * @return list<string>
 */
function chatbotKnowledgeSearchTerms(string $message): array
{
    $expanded = chatbotExpandShortForms($message);
    $normalized = strtolower(trim($expanded));
    $fillers = [
        'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'what', 'whats', 'who', 'when', 'where', 'why', 'how',
        'please', 'tell', 'me', 'you', 'about', 'for', 'of', 'to', 'in', 'on',
        'can', 'could', 'would', 'should', 'do', 'does', 'did',
        'meaning', 'mean', 'means', 'definition', 'definitions', 'define', 'defn',
        'explain', 'explained', 'describe', 'info', 'information',
    ];
    $stop = array_flip($fillers);
    $terms = [];
    $seen = [];

    $add = static function (string $term) use (&$terms, &$seen): void {
        $term = trim($term);
        if ($term === '' || isset($seen[$term])) {
            return;
        }
        $seen[$term] = true;
        $terms[] = $term;
    };

    $add($message);
    $add($expanded);

    $core = chatbotExtractDefinitionTerm($message);
    if ($core !== '') {
        $add($core);
    }

    $words = preg_split('/\s+/', preg_replace('/[^\w\s-]/', ' ', $normalized)) ?: [];
    $significant = [];
    foreach ($words as $word) {
        if ($word === '' || isset($stop[$word]) || strlen($word) < 2) {
            continue;
        }
        $significant[] = $word;
        if (strlen($word) >= 3 || in_array($word, ['poa', 'id'], true)) {
            $add($word);
        }
    }

    if ($significant !== []) {
        $add(implode(' ', $significant));
    }

    return $terms;
}

function chatbotExtractDefinitionTerm(string $message): string
{
    $term = strtolower(trim($message));
    $term = preg_replace(
        '/\b(what is|what are|what\'s|whats|whats the|meaning of|definition of|'
        . 'tell me about|info on|information on|explain|define|describe)\b/i',
        ' ',
        $term
    ) ?? $term;
    $term = preg_replace('/\b(meaning|definition|definitions|defn|means|mean)\b/i', ' ', $term) ?? $term;
    $term = preg_replace('/\s+/', ' ', trim($term)) ?? '';

    if ($term !== '') {
        return $term;
    }

    $subject = chatbotExtractQuestionSubject($message);

    return $subject !== 'your question' ? $subject : '';
}

function chatbotLooksLikeKnowledgeQuery(string $message): bool
{
    $normalized = strtolower(trim($message));

    if ($normalized === '' || chatbotIsDraftRequest($message)) {
        return false;
    }

    if (chatbotWantsCount($normalized) || chatbotWantsList($normalized)) {
        return false;
    }

    if (preg_match(
        '/\b(how many|list|show|dashboard|total revenue|our clients|my clients|pending invoice|'
        . 'upcoming appointment|unread notification|morning briefing)\b/',
        $normalized
    )) {
        return false;
    }

    if (preg_match('/\b(meaning|definition|defn|means|define|explain|what is|what are|what\'s|whats)\b/i', $message)) {
        return !chatbotMessageRefersToPortalClient($message);
    }

    if (preg_match('/^(define|explain)\s+\S/i', trim($message))) {
        return true;
    }

    $wordCount = str_word_count($normalized);
    if ($wordCount <= 4 && !chatbotIsSystemDataQuestion($message)) {
        if (preg_match(
            '/\b(apostille|affidavit|notary|notaris|jurat|acknowledg|legaliz|hague|'
            . 'attestation|oath|witness|signer|deed|will|probate|immigration|'
            . 'certif|copy|journal|impartial|fraud|id|identification)\b/i',
            $normalized
        )) {
            return true;
        }
    }

    if ($wordCount <= 2 && !preg_match('/\b(clients?|cases?|payments?|appointments?|invoices?|notifications?)\b/', $normalized)) {
        if (chatbotMessageRefersToPortalClient($message)) {
            return false;
        }

        return strlen(preg_replace('/\s+/', '', $normalized)) >= 3;
    }

    return false;
}

function chatbotIsDefinitionRequest(string $message): bool
{
    return chatbotLooksLikeKnowledgeQuery($message);
}

/**
 * Flexible knowledge lookup — does not require exact phrasing ("apostille meaning", "poa", etc.).
 */
function chatbotReplyForFlexibleKnowledge(string $message): ?string
{
    if (chatbotIsDraftRequest($message)) {
        return null;
    }

    if (chatbotMessageRefersToPortalClient($message)) {
        return null;
    }

    if (chatbotIsSystemDataQuestion($message) && (chatbotWantsList($message) || chatbotWantsCount($message))) {
        return null;
    }

    foreach (chatbotKnowledgeSearchTerms($message) as $term) {
        if ($term === '' || strlen($term) < 2) {
            continue;
        }

        $general = chatbotReplyForGeneralKnowledge($term);
        if ($general !== null) {
            return $general;
        }

        $expanded = chatbotReplyForExpandedKnowledge($term);
        if ($expanded !== null) {
            return $expanded;
        }
    }

    $fused = chatbotFuseKnowledgeByKeywords($message);
    if ($fused !== null) {
        return $fused;
    }

    return null;
}

function chatbotIsGeneralKnowledgeQuestion(string $message): bool
{
    if (chatbotIsDraftRequest($message)) {
        return false;
    }

    // Portal metrics (clients, revenue, appointments, etc.) always use live data — never Wikipedia/templates.
    if (chatbotIsSystemDataQuestion($message) || chatbotIsPortalSystemQuestion($message)) {
        return false;
    }

    if (chatbotMessageRefersToPortalClient($message) || chatbotLooksLikePersonNameSearch($message)) {
        return false;
    }

    return chatbotLooksLikeKnowledgeQuery($message)
        || chatbotIsGeneralQuestion($message)
        || chatbotIsAdviceOrHowToQuery($message);
}

function chatbotLooksLikePersonNameSearch(string $message): bool
{
    if (preg_match('/case[- ]?\d{4}[- ]?\d+/i', $message)) {
        return true;
    }

    if (chatbotMessageRefersToPortalClient($message)) {
        return true;
    }

    if (preg_match('/\b(client|customer|profile|who is|details for|information on|info on)\b/i', $message)) {
        $term = chatbotNormalizeLookupTerm($message);
        if ($term !== '' && str_word_count($term) <= 4 && !chatbotIsSystemDataQuestion($message)) {
            return true;
        }
    }

    $term = chatbotNormalizeLookupTerm($message);
    if ($term === '' || str_word_count($term) < 2) {
        return false;
    }

    if (chatbotLooksLikeKnowledgeQuery($message) || chatbotIsSystemDataQuestion($message)) {
        return false;
    }

    return str_word_count($term) <= 4;
}

function chatbotWikipediaEnabled(): bool
{
    $config = require __DIR__ . '/../config/config.php';

    return !empty($config['chatbot']['wikipedia']['enabled']);
}

function chatbotFetchWikipediaSummary(string $term): ?string
{
    $term = trim($term);
    if ($term === '' || strlen($term) > 200 || !chatbotWikipediaEnabled()) {
        return null;
    }

    $title = chatbotWikipediaResolveTitle($term);
    if ($title === null) {
        return null;
    }

    $url = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($title);
    $payload = chatbotHttpGetJson($url);
    if (!is_array($payload)) {
        return null;
    }

    $extract = trim((string) ($payload['extract'] ?? ''));
    if ($extract === '' || ($payload['type'] ?? '') === 'disambiguation') {
        return null;
    }

    $display = (string) ($payload['title'] ?? $title);
    $link = (string) ($payload['content_urls']['desktop']['page'] ?? 'https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title)));

    return "**{$display}** (from Wikipedia)\n\n"
        . $extract
        . "\n\n[Read more on Wikipedia]({$link})"
        . "\n\n_Note: For notary-specific workflow, also check your portal data or ask about a **client** or **case**._";
}

function chatbotWikipediaResolveTitle(string $term): ?string
{
    $direct = chatbotHttpGetJson(
        'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $term))
    );
    if (is_array($direct) && !empty($direct['extract']) && ($direct['type'] ?? '') !== 'disambiguation') {
        return (string) ($direct['title'] ?? $term);
    }

    $searchUrl = 'https://en.wikipedia.org/w/api.php?'
        . http_build_query([
            'action'   => 'query',
            'list'     => 'search',
            'srsearch' => $term,
            'srlimit'  => 1,
            'format'   => 'json',
        ]);
    $search = chatbotHttpGetJson($searchUrl);
    $title = $search['query']['search'][0]['title'] ?? null;

    return is_string($title) && $title !== '' ? $title : null;
}

/**
 * @return array<string, mixed>|null
 */
function chatbotHttpGetJson(string $url): ?array
{
    $userAgent = 'CaseNotaryAdminBot/1.0 (notary management assistant; +https://localhost)';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: ' . $userAgent,
            ],
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code < 200 || $code >= 400) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header'  => "Accept: application/json\r\nUser-Agent: {$userAgent}\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        return null;
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * Local KB + Wikipedia (offline). LLM is added in ChatbotService.
 */
function chatbotReplyForUniversalKnowledgeOffline(string $message): ?string
{
    if (chatbotIsDraftRequest($message)) {
        return null;
    }

    if (chatbotMessageRefersToPortalClient($message)) {
        return null;
    }

    if (chatbotIsSystemDataQuestion($message) && (chatbotWantsList($message) || chatbotWantsCount($message))) {
        return null;
    }

    $local = chatbotReplyForFlexibleKnowledge($message);
    if ($local !== null) {
        return $local;
    }

    foreach (chatbotKnowledgeSearchTerms($message) as $term) {
        if ($term === '' || strlen($term) < 2) {
            continue;
        }

        $wiki = chatbotFetchWikipediaSummary($term);
        if ($wiki !== null) {
            return $wiki;
        }
    }

    return null;
}

function chatbotReplyForDraftRequest(string $message): ?string
{
    if (!chatbotIsDraftRequest($message)) {
        return null;
    }

    $draft = chatbotTemplateDraftContent($message);
    chatbotRememberDraft($draft);

    return $draft . "\n\n_Say **save draft to CASE-2026-0001** to apply as client instructions (confirm with yes)._";
}

function chatbotReplyForDefinitionRequest(string $message): ?string
{
    if (!chatbotLooksLikeKnowledgeQuery($message) && !chatbotIsDefinitionRequest($message)) {
        return null;
    }

    return chatbotReplyForUniversalKnowledgeOffline($message);
}

function chatbotTemplateDefinition(string $subject, string $message): string
{
    $known = chatbotReplyForFlexibleKnowledge($message);
    if ($known !== null) {
        return $known;
    }

    $subject = trim($subject) !== '' ? trim($subject) : 'this term';

    return "**{$subject}** — a term used in legal or notarial contexts; meaning varies by **jurisdiction**. "
        . '_General information only, not legal advice._';
}

function chatbotDetectDraftDocumentType(string $message): string
{
    $m = strtolower($message);

    $map = [
        'email'              => '/\b(email|e-mail)\b/',
        'appointment_confirm'=> '/\b(appointment confirmation|confirm appointment|booking confirmation)\b/',
        'reminder'           => '/\b(reminder|follow.?up|overdue reminder)\b/',
        'quotation'          => '/\b(quotation|quote|estimate|fee proposal)\b/',
        'invoice'            => '/\binvoice\b/',
        'receipt'            => '/\breceipt\b/',
        'affidavit'          => '/\baffidavit\b/',
        'power_of_attorney'  => '/\b(power of attorney|poa|lasting power|lpa)\b/',
        'acknowledgment'     => '/\b(acknowledgment|acknowledgement|jurat|notarial certificate)\b/',
        'client_instructions'=> '/\b(client instructions|instructions for client)\b/',
        'checklist'          => '/\bchecklist\b/',
        'memo'               => '/\b(memo|memorandum)\b/',
        'nda'                => '/\b(nda|non-disclosure|confidentiality agreement)\b/',
        'contract'           => '/\b(contract|agreement)\b/',
        'policy'             => '/\b(policy|procedure|standard operating)\b/',
        'minutes'            => '/\b(minutes|meeting notes)\b/',
        'cover_letter'       => '/\bcover letter\b/',
        'demand_letter'      => '/\b(demand letter|payment demand)\b/',
        'sworn_statement'    => '/\b(sworn statement|statutory declaration|declaration)\b/',
        'certificate'        => '/\b(certificate|certification|attestation)\b/',
        'report'             => '/\b(report|summary)\b/',
        'proposal'           => '/\bproposal\b/',
        'letter'             => '/\b(letter|message|reply|response|client letter)\b/',
    ];

    foreach ($map as $type => $pattern) {
        if (preg_match($pattern, $m)) {
            return $type;
        }
    }

    return 'general';
}

function chatbotTemplateDraftContent(string $message): string
{
    $company = getCompanySettings();
    $brand   = companyBrandName($company);
    $subject = chatbotExtractQuestionSubject($message);
    $type    = chatbotDetectDraftDocumentType($message);
    $footer  = "\n\n_Not legal advice. Edit all [brackets] before use. For portal PDFs use **Cases → Client Letter** or **Generate Quotation**._";

    switch ($type) {
        case 'email':
            return "**Draft email** (edit before sending):\n\n"
                . "**Subject:** [Matter reference] — {$brand}\n\n"
                . "Dear [Client name],\n\n"
                . "Thank you for contacting **{$brand}** regarding [brief topic].\n\n"
                . "[Body — explain status, what you need from the client, or next steps.]\n\n"
                . "**Please note:**\n"
                . "• [Document or ID required]\n"
                . "• [Appointment date/time if applicable]\n"
                . "• [Fee or payment instructions]\n\n"
                . "Reply to this email or sign in to the **client portal** for updates.\n\n"
                . "Kind regards,\n[Your name]\n[Title]\n{$brand}" . $footer;

        case 'appointment_confirm':
            return "**Draft appointment confirmation:**\n\n"
                . "Dear [Client name],\n\n"
                . "This confirms your appointment with **{$brand}**:\n\n"
                . "• **Date:** [Date]\n"
                . "• **Time:** [Start time]\n"
                . "• **Location:** [Office address / video link]\n"
                . "• **Purpose:** [e.g. document signing / affidavit]\n\n"
                . "**Please bring:**\n"
                . "• Valid government-issued photo ID\n"
                . "• [Unsigned document(s) if applicable]\n"
                . "• [Witnesses if required]\n\n"
                . "To reschedule, contact us at [phone/email] at least [X] hours in advance.\n\n"
                . "Regards,\n{$brand}" . $footer;

        case 'reminder':
            return "**Draft reminder** (payment / appointment / documents):\n\n"
                . "Dear [Client name],\n\n"
                . "Re: [Case / invoice reference]\n\n"
                . "This is a friendly reminder that [describe item — e.g. invoice #___ due on [date] / documents still required / appointment on [date]].\n\n"
                . "**Action requested by [deadline]:**\n"
                . "[Clear single action]\n\n"
                . "If already completed, please disregard. Contact us with any questions.\n\n"
                . "Sincerely,\n{$brand}" . $footer;

        case 'quotation':
            return "**Draft quotation** (fee estimate):\n\n"
                . "**{$brand}** — Quotation\n"
                . "**Date:** [Date] · **Valid until:** [Date]\n"
                . "**Client:** [Name] · **Matter:** [Case / service description]\n\n"
                . "| Service | Description | Fee |\n"
                . "|---------|-------------|-----|\n"
                . "| Notarial act | [e.g. acknowledgment / affidavit] | [Amount] |\n"
                . "| Travel / extras | [If applicable] | [Amount] |\n"
                . "| **Total** | | **[Total]** |\n\n"
                . "**Terms:** Fees exclude third-party costs (apostille, courier). Payment due [before/on completion].\n\n"
                . "Accepted by: _________________________ Date: _________\n\n"
                . "_Generate a formal PDF from the **case workspace → Quotation**._" . $footer;

        case 'invoice':
            return "**Draft invoice** outline:\n\n"
                . "**Invoice #:** [Number] · **Date:** [Date] · **Due:** [Due date]\n"
                . "**Bill to:** [Client name & address]\n"
                . "**Re:** [Case / matter]\n\n"
                . "| Description | Qty | Rate | Amount |\n"
                . "|-------------|-----|------|--------|\n"
                . "| [Service line] | 1 | [Rate] | [Amount] |\n"
                . "| **Total due** | | | **[Total]** |\n\n"
                . "**Payment:** [Bank details / link / method]\n\n"
                . "Thank you for your business.\n{$brand}" . $footer;

        case 'receipt':
            return "**Draft payment receipt:**\n\n"
                . "**Receipt #:** [Number] · **Date:** [Date]\n"
                . "Received from **[Client name]** the sum of **[Amount]** for **[Invoice / matter reference]**.\n"
                . "Payment method: [Cash / card / transfer].\n\n"
                . "Issued by: {$brand}\n[Signature]" . $footer;

        case 'affidavit':
            return "**Draft affidavit** (skeleton — solicitor may need to review substantive facts):\n\n"
                . "**IN THE [COURT / TRIBUNAL NAME]**\n"
                . "**Case / matter:** [Reference]\n\n"
                . "**AFFIDAVIT OF [FULL NAME]**\n\n"
                . "I, **[Full name]**, of **[Address]**, **[Occupation]**, MAKE OATH and say:\n\n"
                . "1. [Background — who you are and your connection to the matter.]\n"
                . "2. [Material fact — what happened, when, where.]\n"
                . "3. [Further facts as numbered paragraphs.]\n\n"
                . "SWORN at [Place] this ___ day of _________ 20__.\n\n"
                . "_________________________\n[Deponent signature]\n\n"
                . "Before me, a Commissioner for Oaths / Notary Public.\n\n"
                . "_Notary: verify ID, administer oath, complete notarial certificate._" . $footer;

        case 'power_of_attorney':
            return "**Draft power of attorney** (outline only — laws vary by jurisdiction):\n\n"
                . "**GENERAL / SPECIAL POWER OF ATTORNEY**\n\n"
                . "I, **[Principal full name]**, of **[Address]**, appoint **[Agent name]**, of **[Address]**, as my attorney-in-fact to act on my behalf.\n\n"
                . "**Powers granted:** [List specific powers — e.g. sign documents, manage property, banking.]\n\n"
                . "**Effective:** [Date] · **Expires:** [Date or event]\n\n"
                . "Signed: _________________________ Date: _________\n"
                . "Witness 1: _________________________\n"
                . "Witness 2: _________________________\n\n"
                . "_Confirm witnessing and notarization rules for your jurisdiction before use._" . $footer;

        case 'acknowledgment':
            return "**Draft acknowledgment certificate** (notarial wording — adjust to local form):\n\n"
                . "State of [State] · County of [County]\n\n"
                . "On this ___ day of _________, 20__, before me personally appeared **[Signer name]**, "
                . "known to me (or proved to me on the basis of satisfactory evidence) to be the person whose name is subscribed to the within instrument, "
                . "and acknowledged that they executed the same for the purposes therein contained.\n\n"
                . "In witness whereof I have hereunto set my hand and official seal.\n\n"
                . "_________________________\nNotary Public\n[Commission expires: ___]" . $footer;

        case 'client_instructions':
            return "**Draft client instructions** (for case **Instructions for Client** field):\n\n"
                . "**What you need to do**\n"
                . "1. [Primary action — e.g. attend appointment with unsigned documents]\n"
                . "2. [Secondary action]\n\n"
                . "**Bring to your appointment**\n"
                . "• Valid photo ID ([acceptable types])\n"
                . "• [Document names — unsigned]\n"
                . "• [Witnesses if required]\n\n"
                . "**Fees & timing**\n"
                . "• Estimated fee: [Amount]\n"
                . "• Typical turnaround: [X business days]\n\n"
                . "**Contact:** [Phone] · [Email]\n{$brand}" . $footer;

        case 'checklist':
            return "**Draft checklist** — [Matter type]:\n\n"
                . "☐ Client identity verified (ID type: ______)\n"
                . "☐ Case opened in portal with correct client & service type\n"
                . "☐ Client instructions sent\n"
                . "☐ Documents received and reviewed\n"
                . "☐ Appointment scheduled: [Date/time]\n"
                . "☐ Notarial act completed; journal entry made\n"
                . "☐ Copies/scans uploaded to case\n"
                . "☐ Invoice issued & payment received\n"
                . "☐ Apostille / legalization ordered (if needed)\n"
                . "☐ Case status updated to Completed/Closed\n\n"
                . "{$brand}" . $footer;

        case 'memo':
            return "**Draft internal memo:**\n\n"
                . "**To:** [Team / person] · **From:** [Your name] · **Date:** [Date]\n"
                . "**Re:** {$subject}\n\n"
                . "**Summary:** [1-2 sentences]\n\n"
                . "**Background:** [Context]\n\n"
                . "**Recommendation / next steps:**\n"
                . "1. [Action]\n"
                . "2. [Action]\n\n"
                . "**Deadline:** [Date]" . $footer;

        case 'nda':
            return "**Draft confidentiality / NDA** (simplified outline):\n\n"
                . "This Agreement is between **{$brand}** (“Disclosing Party”) and **[Recipient]** (“Receiving Party”).\n\n"
                . "1. **Confidential information** means [define scope].\n"
                . "2. Receiving Party will not disclose except as required by law.\n"
                . "3. Term: [X] years from [date].\n"
                . "4. Governing law: [Jurisdiction].\n\n"
                . "Signed: _________________________ Date: _________\n\n"
                . "_Have qualified counsel review before execution._" . $footer;

        case 'contract':
            return "**Draft agreement** (general outline):\n\n"
                . "**Agreement** between **[Party A]** and **[Party B]** dated [Date].\n\n"
                . "1. **Services / scope:** [Description]\n"
                . "2. **Fees & payment:** [Terms]\n"
                . "3. **Term & termination:** [Dates / notice period]\n"
                . "4. **Liability:** [Limitations as advised by counsel]\n"
                . "5. **Signatures:**\n\n"
                . "Party A: _________________________ Date: _________\n"
                . "Party B: _________________________ Date: _________" . $footer;

        case 'policy':
            return "**Draft office policy** — {$subject}:\n\n"
                . "**Purpose:** [Why this policy exists]\n"
                . "**Scope:** All staff handling notarial matters at {$brand}\n"
                . "**Policy:**\n"
                . "1. [Rule / requirement]\n"
                . "2. [Rule / requirement]\n"
                . "**Procedure:** [Step-by-step]\n"
                . "**Review date:** [Annual review]" . $footer;

        case 'minutes':
            return "**Draft meeting minutes:**\n\n"
                . "**Meeting:** [Title] · **Date:** [Date] · **Attendees:** [Names]\n\n"
                . "**Agenda items discussed:**\n"
                . "1. [Topic] — [Summary / decision]\n"
                . "2. [Topic] — [Summary / decision]\n\n"
                . "**Action items:**\n"
                . "| Owner | Task | Due |\n"
                . "|-------|------|-----|\n"
                . "| [Name] | [Task] | [Date] |" . $footer;

        case 'demand_letter':
            return "**Draft demand letter:**\n\n"
                . "[Date]\n\n"
                . "[Debtor name & address]\n\n"
                . "Dear [Name],\n\n"
                . "Re: Outstanding amount of **[Amount]** for **[Invoice / matter]**\n\n"
                . "Despite our previous [invoice/reminder dated ___], payment remains outstanding. "
                . "We require payment in full by **[Deadline date]**.\n\n"
                . "If payment is not received, we may [escalation — e.g. suspend services / refer to collections] without further notice.\n\n"
                . "Payment details: [Instructions]\n\n"
                . "Yours sincerely,\n{$brand}" . $footer;

        case 'sworn_statement':
            return "**Draft sworn statement / declaration:**\n\n"
                . "I, **[Full name]**, of **[Address]**, solemnly declare:\n\n"
                . "1. [Fact]\n"
                . "2. [Fact]\n\n"
                . "I make this declaration conscientiously believing it to be true.\n\n"
                . "Declared at [Place] on [Date].\n\n"
                . "_________________________\n[Declarant]\n\n"
                . "_Before a commissioner for oaths / notary as required locally._" . $footer;

        case 'certificate':
            return "**Draft certificate / attestation:**\n\n"
                . "I certify that **[description — e.g. this is a true copy of the original]** presented to me on [Date] by **[Name]**, "
                . "whose identity was verified by **[ID type and number]**.\n\n"
                . "Place: [Location] · Date: [Date]\n\n"
                . "_________________________\n[Notary / certifier name]\n{$brand}" . $footer;

        case 'proposal':
            return "**Draft service proposal:**\n\n"
                . "**Prepared for:** [Client] · **By:** {$brand} · **Date:** [Date]\n\n"
                . "**Understanding your needs:** [Summary]\n\n"
                . "**Proposed services:**\n"
                . "• [Service 1] — [Outcome]\n"
                . "• [Service 2] — [Outcome]\n\n"
                . "**Timeline:** [Milestones]\n"
                . "**Investment:** [Fee range or fixed fee]\n\n"
                . "**Next step:** [e.g. sign quotation / book appointment]" . $footer;

        case 'letter':
            return "**Draft letter:**\n\n"
                . "[Date]\n\n"
                . "Dear [Recipient name],\n\n"
                . "Re: [Reference]\n\n"
                . "[Opening — purpose of letter regarding {$subject}.]\n\n"
                . "[Body paragraphs.]\n\n"
                . "[Closing action requested.]\n\n"
                . "Yours sincerely,\n[Your name]\n{$brand}" . $footer;

        case 'cover_letter':
            return "**Draft cover letter** (with enclosed documents):\n\n"
                . "Dear [Recipient],\n\n"
                . "Please find enclosed [list documents] for [purpose].\n\n"
                . "[Brief explanation and any instructions for the recipient.]\n\n"
                . "Contact [phone/email] with questions.\n\n"
                . "Sincerely,\n{$brand}" . $footer;

        case 'report':
        default:
            return "**Draft document** — {$subject}:\n\n"
                . "**Title:** [Document title]\n"
                . "**Prepared by:** {$brand} · **Date:** [Date]\n"
                . "**For:** [Client / authority / recipient]\n\n"
                . "**Executive summary**\n"
                . "[2-4 sentences]\n\n"
                . "**Background**\n"
                . "[Context and facts]\n\n"
                . "**Details / analysis**\n"
                . "• [Point 1]\n"
                . "• [Point 2]\n"
                . "• [Point 3]\n\n"
                . "**Conclusion & recommendations**\n"
                . "[Clear next steps]\n\n"
                . "**Signatures**\n"
                . "_________________________\n[Name, title]" . $footer;
    }
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

    if (chatbotIsDraftRequest($message)) {
        return chatbotTemplateDraftContent($message);
    }

    if (chatbotIsDefinitionRequest($message)) {
        return chatbotReplyForDefinitionRequest($message);
    }

    $flex = chatbotReplyForFlexibleKnowledge($message);
    if ($flex !== null) {
        return $flex;
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

    return implode("\n\n---\n\n", array_slice($candidates, 0, 1));
}

/**
 * Questions about this admin portal, its modules, workflows, or live business data.
 */
function chatbotIsPortalSystemQuestion(string $message): bool
{
    if (chatbotIsSystemDataQuestion($message)) {
        return true;
    }

    if (chatbotIsDraftRequest($message) || chatbotMessageRefersToPortalClient($message)) {
        return false;
    }

    $normalized = strtolower(trim($message));

    if (chatbotLooksLikeKnowledgeQuery($message)
        && !preg_match(
            '/\b(portal|admin|system|dashboard|software|app|module|feature|client|case|invoice|'
            . 'payment|appointment|notification|settings|document|upload|letter|quotation|receipt|'
            . 'login|password|smtp|calendar|stripe|workflow|screen|page|tab|button|form)\b/',
            $normalized
        )) {
        return false;
    }

    if (chatbotIsProceduralQuery($message)) {
        return (bool) preg_match(
            '/\b(portal|admin|system|dashboard|software|app|module|feature|client|case|invoice|'
            . 'payment|appointment|notification|settings|document|upload|letter|quotation|receipt|'
            . 'login|password|smtp|calendar|stripe|workflow|screen|page|tab|button|form|'
            . 'where do i|where can i|where is|what page|which page|how do i|how to)\b/',
            $normalized
        );
    }

    return (bool) preg_match(
        '/\b(portal|admin panel|admin portal|this system|this app|this software|notary management|'
        . 'dashboard|sidebar|navigation|menu|module|feature|capabilit|'
        . 'where do i|where can i|where is|what page|which page|how do i use|how does the system|'
        . 'client portal|case view|case form|client form|record payment|mark paid|'
        . 'appointment request|requested status|google calendar|office hours|branding|'
        . 'smtp|stripe|reminder|unread notification|ai assistant|chatbot)\b/i',
        $normalized
    );
}

/**
 * @return list<array{keywords: list<string>, answer: string, pattern?: string}>
 */
function chatbotPortalFaqCatalog(): array
{
    $clientsLink = chatbotAdminLink('pages/clients.php', 'Open clients');
    $casesLink = chatbotAdminLink('pages/cases.php', 'Open cases');
    $paymentsLink = chatbotAdminLink('pages/payments.php', 'Open payments');
    $apptsLink = chatbotAdminLink('pages/appointments.php', 'Open appointments');
    $notifLink = chatbotAdminLink('pages/notifications.php', 'Open notifications');
    $settingsLink = chatbotAdminLink('pages/settings.php', 'Open settings');
    $dashLink = chatbotAdminLink('pages/dashboard.php', 'Open dashboard');
    $aiLink = chatbotAdminLink('pages/chatbot.php', 'Open AI Assistant');

    return [
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|where\s+(?:do|can)\s+i)\b.*\b(?:create|add|new|make)\b.*\bcases?\b|\b(?:create|add|new|make)\b(?:\s+a)?\s+cases?\b/i',
            'keywords' => ['create case', 'new case', 'add case', 'how to create a case'],
            'answer'   => "**How to create a case:**\n\n"
                . "1. Sidebar → **Cases** → **New Case**.\n"
                . "2. Select the **client**.\n"
                . "3. Enter title, service type, fees, and deadline.\n"
                . "4. Add **Instructions for Client**.\n"
                . "5. Click **Save** (optionally email quotation/letter on save).\n\n"
                . $casesLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|change|update|set)\b.*\bcase\b.*\bstatus\b|\bcase\b.*\bstatus\b.*\b(change|update|set)\b/i',
            'keywords' => ['case status', 'update status', 'change status'],
            'answer'   => "**How to update case status:**\n\n"
                . "1. Open **Cases** and click the case (or use **Case View**).\n"
                . "2. Click **Edit**.\n"
                . "3. Change **Status** (Pending, In Progress, Waiting for Client, Completed, Closed).\n"
                . "4. Save.\n\n"
                . $casesLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|where)\b.*\b(?:add|create|register|new)\b.*\bclients?\b|\b(?:add|create|register|new)\b(?:\s+a)?\s+clients?\b/i',
            'keywords' => ['add client', 'create client', 'new client'],
            'answer'   => "**How to add a client:**\n\n"
                . "1. Sidebar → **Clients** → **Add Client**.\n"
                . "2. Enter name, email, phone, and company (if any).\n"
                . "3. Save.\n"
                . "4. Optionally create **portal login** credentials on the client profile.\n\n"
                . $clientsLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|where)\b.*\b(?:upload|attach|add)\b.*\b(?:document|file|pdf)\b/i',
            'keywords' => ['upload document', 'attach file', 'add document'],
            'answer'   => "**How to upload a document:**\n\n"
                . "1. Open the **case** (**Cases** → click the case).\n"
                . "2. Go to the documents section on **Case View**.\n"
                . "3. Upload the file (PDF, image, etc.).\n"
                . "4. The client will see shared files on their portal case page.\n\n"
                . $casesLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|generate|create|send)\b.*\b(?:quotation|quote|client letter)\b/i',
            'keywords' => ['quotation', 'client letter', 'generate quote'],
            'answer'   => "**How to generate a quotation or client letter:**\n\n"
                . "1. Open the **case**.\n"
                . "2. Use **Generate Quotation** or the **Client Letter** tab.\n"
                . "3. Review the PDF and email or download as needed.\n\n"
                . $casesLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|schedule|book|create)\b.*\bappointments?\b/i',
            'keywords' => ['schedule appointment', 'book appointment', 'new appointment'],
            'answer'   => "**How to schedule an appointment:**\n\n"
                . "1. Sidebar → **Appointments**.\n"
                . "2. Create or open an appointment.\n"
                . "3. Select **client**, date/time, and status (**Scheduled** or **Confirmed**).\n"
                . "4. Save.\n\n"
                . $apptsLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|approve)\b.*\b(?:request|requested)\b.*\bappointments?\b|\bappointments?\b.*\brequested\b/i',
            'keywords' => ['appointment request', 'approve appointment', 'requested appointment'],
            'answer'   => "**How to approve a client appointment request:**\n\n"
                . "1. Go to **Appointments**.\n"
                . "2. Open the appointment with status **Requested**.\n"
                . "3. Set status to **Scheduled** or **Confirmed** and set the date/time.\n"
                . "4. Save.\n\n"
                . $apptsLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|record|mark)\b.*\b(?:payment|paid|receipt)\b/i',
            'keywords' => ['record payment', 'mark paid'],
            'answer'   => "**How to record a payment:**\n\n"
                . "1. Go to **Payments** or the case’s payment section.\n"
                . "2. Open the invoice.\n"
                . "3. Record the amount and payment method.\n"
                . "4. Save — status updates to paid or partially paid.\n\n"
                . $paymentsLink,
        ],
        [
            'pattern'  => '/\b(where\s+(?:is|are)|how\s+(?:to|do\s+i)\s+(?:find|open|get\s+to))\b.*\bsettings\b/i',
            'keywords' => ['where are settings', 'find settings', 'open settings'],
            'answer'   => "**Where to find Settings:** Sidebar → **Settings** (gear icon at the bottom of the menu).\n\n" . $settingsLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|set\s+up|configure)\b.*\b(?:smtp|email)\b/i',
            'keywords' => ['smtp', 'email settings'],
            'answer'   => "**How to configure email (SMTP):**\n\n"
                . "1. Go to **Settings**.\n"
                . "2. Enter SMTP host, port, username, and password.\n"
                . "3. Save and send a test email if available.\n\n"
                . $settingsLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|connect|set\s+up|sync)\b.*\bgoogle\s+calendar\b/i',
            'keywords' => ['google calendar', 'calendar sync'],
            'answer'   => "**How to connect Google Calendar:**\n\n"
                . "1. Go to **Settings**.\n"
                . "2. Use the Google Calendar integration section.\n"
                . "3. Connect your account and authorize access.\n"
                . "4. Save — new appointments can sync to your calendar.\n\n"
                . $settingsLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|set\s+up|configure)\b.*\bstripe\b/i',
            'keywords' => ['stripe', 'online payment'],
            'answer'   => "**How to set up Stripe:**\n\n"
                . "1. Go to **Settings**.\n"
                . "2. Enter your Stripe API keys.\n"
                . "3. Save — clients can then pay invoices online from their portal.\n\n"
                . $settingsLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|add|set)\b.*\bclient\s+instructions\b/i',
            'keywords' => ['client instructions', 'instructions for client'],
            'answer'   => "**How to add client instructions:**\n\n"
                . "1. When creating or editing a case, fill in **Instructions for Client**.\n"
                . "2. List what to bring, complete, or prepare.\n"
                . "3. Save — clients see this highlighted on their case view.\n\n"
                . $casesLink,
        ],
        [
            'pattern'  => '/\b(how\s+(?:to|do\s+i)|open|view|find)\b.*\bcase\b(?!.*\b(?:create|new|add|status)\b)/i',
            'keywords' => ['case view', 'open case', 'find case'],
            'answer'   => "**How to open a case:**\n\n"
                . "1. Go to **Cases**.\n"
                . "2. Click the case row, or search by case number.\n"
                . "3. **Case View** shows documents, payments, letters, and status.\n\n"
                . $casesLink,
        ],
        [
            'pattern'  => '/\b(case|cases)\b.*\b(workflow|overview|in general|explain)\b|\b(workflow|overview)\b.*\bcases?\b/i',
            'keywords' => ['case workflow', 'cases overview'],
            'answer'   => "**Case workflow (overview):**\n\n"
                . "• **New Case** — client, services, fees, instructions.\n"
                . "• **Case View** — documents, letters, payments, status.\n"
                . "• **Statuses** — Pending → In Progress → Waiting for Client → Completed/Closed.",
        ],
        [
            'pattern'  => '/\b(where\s+(?:is|are)|what\s+is)\b.*\bdashboard\b/i',
            'keywords' => ['dashboard', 'where is dashboard'],
            'answer'   => "**Dashboard** is the first item in the sidebar. It shows clients, active cases, revenue, pending invoices, and upcoming appointments.\n\n" . $dashLink,
        ],
        [
            'pattern'  => '/\b(edit|update)\b.*\bclients?\b/i',
            'keywords' => ['edit client', 'update client'],
            'answer'   => "**How to edit a client:** **Clients** → click the client → **Edit** → update details → Save. Portal login can be created or reset from the same profile.\n\n" . $clientsLink,
        ],
        [
            'pattern'  => '/\bdelete\b.*\bclients?\b/i',
            'keywords' => ['delete client'],
            'answer'   => "**How to delete a client:** Open the client → **Delete**. This removes their portal user and linked case files and cannot be undone.\n\n" . $clientsLink,
        ],
        [
            'pattern'  => '/\b(how\s+do|what\s+are)\b.*\bnotifications?\b.*\bwork\b/i',
            'keywords' => ['how notifications work'],
            'answer'   => "**Notifications:** The bell in the top bar shows recent alerts; the **Notifications** page lists all. You and clients receive notices for cases, appointments, and payments.\n\n" . $notifLink,
        ],
        [
            'pattern'  => '/\bwhat\s+can\s+clients?\b.*\b(?:see|do|access)\b/i',
            'keywords' => ['client portal', 'what can clients see'],
            'answer'   => "**Client portal access:** Clients can view their **cases**, **documents**, **invoices**, **appointments**, and **contact** your office. They can request appointments and upload files per case. Create logins from the client profile.\n\n" . $clientsLink,
        ],
        [
            'pattern'  => '/\b(filter|search)\b.*\blists?\b/i',
            'keywords' => ['filter', 'search list'],
            'answer'   => "**Search & filters:** Use the search box and status dropdown on list pages (Clients, Cases, Payments, etc.). Filters apply automatically when you change them.",
        ],
        [
            'pattern'  => '/\b(case)\b.*\bpriority\b/i',
            'keywords' => ['case priority'],
            'answer'   => "**Case priority** is set on the admin case form only — **clients do not see it** on their portal.",
        ],
        [
            'pattern'  => '/\b(sign\s+out|log\s*out)\b/i',
            'keywords' => ['sign out', 'logout'],
            'answer'   => '**Sign out:** Click **Sign Out** at the bottom of the sidebar.',
        ],
        [
            'pattern'  => '/\b(what can (you|the system)|features? of (the )?(system|portal)|help with the system)\b/i',
            'keywords' => ['what can you do', 'capabilities'],
            'answer'   => 'I answer **one question at a time** — live data (clients, revenue, invoices…), portal how-tos, definitions, and drafts. Ask exactly what you need, e.g. *How do I create a case?*',
        ],
    ];
}

/**
 * Direct answer for the exact question asked (pattern-matched FAQ). Skips counts/lists.
 */
function chatbotReplyForFocusedQuestion(string $message): ?string
{
    if (chatbotWantsCount($message) || chatbotWantsList($message)) {
        return null;
    }

    $normalized = strtolower(trim($message));
    if ($normalized === '') {
        return null;
    }

    foreach (chatbotPortalFaqCatalog() as $entry) {
        if (empty($entry['pattern'])) {
            continue;
        }
        if (preg_match($entry['pattern'], $normalized)) {
            return $entry['answer'];
        }
    }

    return null;
}

function chatbotReplyFromPortalFaq(string $message): ?string
{
    $focused = chatbotReplyForFocusedQuestion($message);
    if ($focused !== null) {
        return $focused;
    }

    $normalized = strtolower(trim($message));
    if ($normalized === '') {
        return null;
    }

    $bestAnswer = null;
    $bestScore  = 0;

    foreach (chatbotPortalFaqCatalog() as $entry) {
        if (!empty($entry['pattern']) && preg_match($entry['pattern'], $normalized)) {
            return $entry['answer'];
        }

        $score = 0;
        foreach ($entry['keywords'] as $keyword) {
            $keyword = strtolower($keyword);
            if ($keyword === '' || !str_contains($normalized, $keyword)) {
                continue;
            }
            $score += max(3, (int) floor(strlen($keyword) / 2));
        }

        if ($score > $bestScore) {
            $bestScore  = $score;
            $bestAnswer = $entry['answer'];
        }
    }

    return $bestScore >= 6 ? $bestAnswer : null;
}

function chatbotPortalSystemFallback(string $message): string
{
    $focused = chatbotReplyForFocusedQuestion($message);
    if ($focused !== null) {
        return $focused;
    }

    $normalized = strtolower(trim($message));
    if (preg_match('/\b(client|case|payment|invoice|appointment|setting|document)\b/', $normalized)) {
        return 'Ask one specific question — e.g. *How do I create a case?*, *Where are settings?*, or *List overdue invoices* — and I will answer only that.';
    }

    return 'Ask a **specific** question about the portal or your data — I answer exactly what you ask, nothing extra.';
}

/**
 * Unified handler for portal / system questions (data + workflows + FAQ).
 */
function chatbotReplyForPortalSystemQuestion(string $message): ?string
{
    if (!chatbotIsPortalSystemQuestion($message) && !chatbotIsProceduralQuery($message)) {
        return null;
    }

    syncOverdueInvoices();

    $handlers = [
        static fn (string $m): ?string => chatbotReplyForFocusedQuestion($m),
        static fn (string $m): ?string => chatbotReplyForPortalDataQuestion($m),
        static fn (string $m): ?string => chatbotReplyForSystemInsights($m),
        static fn (string $m): ?string => chatbotReplyForAppointmentQueries($m),
        static fn (string $m): ?string => chatbotReplyForCaseQueries($m),
        static fn (string $m): ?string => chatbotReplyForNotificationQueries($m),
        static fn (string $m): ?string => chatbotReplyForEntityLookup($m),
        static fn (string $m): ?string => chatbotReplyFromPortalFaq($m),
        static fn (string $m): ?string => ChatbotService::replyFromKnowledgeBase($m),
        static fn (string $m): ?string => ChatbotService::replyForProcedural($m),
    ];

    foreach ($handlers as $handler) {
        $reply = $handler($message);
        if ($reply !== null && trim($reply) !== '') {
            return $reply;
        }
    }

    $generated = generateChatbotReply($message);
    if (!ChatbotService::isGenericFallback($generated)) {
        return $generated;
    }

    if (ChatbotService::hasOptionalLlm()) {
        $llm = ChatbotService::replyViaLlm($message);
        if ($llm !== null && trim($llm) !== '') {
            return $llm;
        }
    }

    $faq = chatbotReplyFromPortalFaq($message);
    if ($faq !== null) {
        return $faq;
    }

    return chatbotPortalSystemFallback($message);
}

function getChatbotSystemSnapshot(): array
{
    $stats  = getDashboardStats();

    return [
        'modules'              => [
            'Dashboard', 'Clients', 'Cases', 'Payments', 'Appointments',
            'Notifications', 'AI Assistant', 'Settings',
        ],
        'clients'              => (int) $stats['total_clients'],
        'active_cases'         => (int) $stats['active_cases'],
        'pending_invoices'     => (int) $stats['pending_invoices'],
        'upcoming_appointments'=> (int) $stats['upcoming_appointments'],
        'total_revenue'        => (float) $stats['total_revenue'],
        'monthly_revenue'      => (float) ($stats['monthly_revenue'] ?? 0),
        'paid_invoices'        => (int) ($stats['paid_invoices'] ?? 0),
    ];
}
