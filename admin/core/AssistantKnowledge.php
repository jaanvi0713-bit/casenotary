<?php



declare(strict_types=1);



class AssistantKnowledge

{

    /** @var array<string, array{short: string, long: string}> */

    private const DEFINITIONS = [

        'jurat' => [

            'short' => 'A jurat certifies that a signer appeared before the notary, was identified, and signed the document under oath or affirmation.',

            'long' => "A **jurat** is a notarial certificate used on affidavits and sworn statements. The notary verifies identity, administers an oath or affirmation, and witnesses the signature. The jurat wording typically states that the signer appeared personally, was known or identified, and signed in the notary's presence.",

        ],

        'acknowledgment' => [

            'short' => 'An acknowledgment confirms a signer appeared, was identified, and acknowledged signing the document voluntarily.',

            'long' => "An **acknowledgment** (or acknowledgement) is the most common notarial act for deeds and contracts. Unlike a jurat, it does not require an oath. The notary confirms identity and that the signer knowingly signed the document.",

        ],

        'affidavit' => [

            'short' => 'A written statement of facts sworn or affirmed to be true, usually taken before a notary.',

            'long' => 'An **affidavit** is a sworn written declaration of facts. It is typically signed under oath before a notary using a jurat certificate.',

        ],

        'apostille' => [

            'short' => 'A certificate authenticating a notary or public official signature for use in another Hague Convention country.',

            'long' => 'An **apostille** is an international authentication certificate under the Hague Apostille Convention. It validates the origin of a public document so it can be recognized abroad.',

        ],

        'power of attorney' => [

            'short' => 'A legal instrument authorizing one person (the agent or attorney-in-fact) to act on another person’s behalf (the principal).',

            'long' => 'A **power of attorney (POA)** grants an agent authority to act for a principal in legal or financial matters. Notaries often acknowledge POA signatures; verify identity, capacity, scope of authority, and whether the principal is signing voluntarily.',

        ],

        'authenticated deed' => [

            'short' => 'A deed executed and certified before a notary, often with identity and capacity checks recorded.',

            'long' => 'An **authenticated deed** is a formal instrument executed before a notary who certifies the execution, identity of parties, and often the substance of the act for evidentiary weight.',

        ],

        'notary public' => [

            'short' => 'A public officer authorized to perform notarial acts such as acknowledgments, jurats, oaths, and certified copies.',

            'long' => 'A **notary public** is a state-appointed official who deters fraud by verifying identity, willingness, and awareness of signers, and by maintaining a record of notarial acts in a journal.',

        ],

        'notarial act' => [

            'short' => 'Any official act a notary is authorized to perform, such as an acknowledgment, jurat, oath, or copy certification.',

            'long' => 'A **notarial act** is the formal procedure performed by a notary, documented with a certificate, seal, and journal entry where required by law.',

        ],

        'notarial certificate' => [

            'short' => 'The wording signed and sealed by a notary that describes the notarial act performed on a document.',

            'long' => 'A **notarial certificate** is the notary’s official statement—often an acknowledgment or jurat block—affixed to or on a document, recording what was done and when.',

        ],

        'notarial seal' => [

            'short' => 'The official stamp or embosser impression identifying the notary and jurisdiction.',

            'long' => 'A **notarial seal** (stamp or embosser) is applied to notarized documents as a security feature and to identify the notary commission and jurisdiction.',

        ],

        'oath' => [

            'short' => 'A solemn promise to tell the truth, often invoking a deity, administered by a notary for affidavits and jurats.',

            'long' => 'An **oath** is a verbal pledge of truthfulness administered by a notary or other official before a sworn statement is signed.',

        ],

        'affirmation' => [

            'short' => 'A solemn declaration of truth without religious wording, used instead of an oath.',

            'long' => 'An **affirmation** serves the same purpose as an oath for affidavits and jurats but without invoking a deity, for signers who object on religious grounds.',

        ],

        'attestation' => [

            'short' => 'A notary’s certification that a signature is genuine or that an act occurred as stated.',

            'long' => '**Attestation** is the notary’s official witnessing or certification of a signature, execution, or fact, often recorded in a notarial certificate.',

        ],

        'witnessing' => [

            'short' => 'Observing a person sign a document and, where required, confirming identity and willingness.',

            'long' => '**Witnessing** by a notary means the notary saw the signing (or received acknowledgment of a prior signature) and verified the signer’s identity according to applicable rules.',

        ],

        'certified copy' => [

            'short' => 'A copy of a document that a notary certifies is a true, complete reproduction of the original.',

            'long' => 'A **certified copy** (copy certification) is created when a notary compares a copy to the original and certifies that it is accurate. Not all jurisdictions allow notaries to certify every document type.',

        ],

        'copy certification' => [

            'short' => 'The notarial act of certifying that a photocopy matches the original document.',

            'long' => '**Copy certification** is a notarial act confirming a reproduction is true and complete. The notary may need to inspect the original and attach a certificate to the copy.',

        ],

        'statutory declaration' => [

            'short' => 'A written statement declared to be true in a prescribed form, often before a notary or commissioner for oaths.',

            'long' => 'A **statutory declaration** is a formal unsworn or affirmed statement made under statute. In many systems a notary or commissioner for oaths may take the declaration.',

        ],

        'sworn statement' => [

            'short' => 'A written declaration of facts given under oath or affirmation before a notary.',

            'long' => 'A **sworn statement** is testimony in writing, taken under oath or affirmation, typically notarized with a jurat.',

        ],

        'deed' => [

            'short' => 'A written instrument that transfers, grants, or confirms an interest in property or creates an obligation.',

            'long' => 'A **deed** is a signed written instrument—such as a transfer, mortgage, or lease—often acknowledged before a notary to be recordable and enforceable.',

        ],

        'deed poll' => [

            'short' => 'A deed made by and binding on one person only, such as a name change or unilateral undertaking.',

            'long' => 'A **deed poll** is executed by a single party (e.g. change of name, declaration, or covenant) and is often acknowledged before a notary.',

        ],

        'conveyance' => [

            'short' => 'A deed or instrument that transfers title or interest in real property.',

            'long' => 'A **conveyance** transfers ownership or rights in land or property. Notaries frequently acknowledge conveyance deeds for registration.',

        ],

        'mortgage deed' => [

            'short' => 'A deed that secures a loan with property as collateral.',

            'long' => 'A **mortgage deed** creates a charge over property in favour of a lender. Execution and acknowledgment before a notary are common for registration.',

        ],

        'will' => [

            'short' => 'A document setting out how a person’s estate should be distributed after death.',

            'long' => 'A **will** (testament) expresses testamentary wishes. Notaries may witness or authenticate wills depending on local law; capacity and voluntary execution are critical.',

        ],

        'testament' => [

            'short' => 'Another term for a will—the disposition of property on death.',

            'long' => 'A **testament** is a will. Notarial involvement may include witnessing, safe custody, or authentication under local succession law.',

        ],

        'probate' => [

            'short' => 'The legal process of proving and administering a deceased person’s will.',

            'long' => '**Probate** validates a will and appoints an executor. Notaries may handle related affidavits, certified copies, and authentications for cross-border estates.',

        ],

        'grant of probate' => [

            'short' => 'A court document confirming a will is valid and authorizing the executor to act.',

            'long' => 'A **grant of probate** gives the executor authority to administer the estate. Notaries may certify copies or authenticate the grant for use abroad.',

        ],

        'letters of administration' => [

            'short' => 'Court authority granted to administer an estate when there is no will or no executor.',

            'long' => '**Letters of administration** appoint an administrator when probate of a will is not available. Notaries may assist with related certificates and copies.',

        ],

        'executor' => [

            'short' => 'A person appointed in a will to administer the deceased’s estate.',

            'long' => 'An **executor** carries out the terms of a will after probate. Notaries should verify authority and identity when documents are signed on behalf of an estate.',

        ],

        'authentication' => [

            'short' => 'Verification that a signature, seal, or document is genuine for official use.',

            'long' => '**Authentication** confirms the origin of a public document or official signature—locally through notarial acts, internationally through apostille or consular legalization.',

        ],

        'legalization' => [

            'short' => 'Consular or governmental certification of a document for use in a foreign country (non-Hague).',

            'long' => '**Legalization** (legalisation) is a chain of authentications—often notary, then foreign ministry, then embassy—for countries not under the Hague Apostille Convention.',

        ],

        'legalisation' => [

            'short' => 'British spelling of legalization—official authentication for international use.',

            'long' => '**Legalisation** is the process of certifying a document through competent authorities so it will be accepted in another country, often after notarization.',

        ],

        'hague convention' => [

            'short' => 'An international treaty simplifying document authentication between member countries via apostille.',

            'long' => 'The **Hague Apostille Convention** abolishes the need for embassy legalization among member states. An apostille issued in one member country is recognized in others.',

        ],

        'commissioner for oaths' => [

            'short' => 'An official authorized to administer oaths and take affidavits, similar to a notary in some jurisdictions.',

            'long' => 'A **commissioner for oaths** may take affidavits, statutory declarations, and affirmations. Roles overlap with notaries depending on local law.',

        ],

        'signing agent' => [

            'short' => 'A notary who specializes in loan document signings, often mobile.',

            'long' => 'A **signing agent** (loan signing agent) is a notary trained to guide borrowers through mortgage closing documents and perform required notarial acts.',

        ],

        'credible witness' => [

            'short' => 'A person who vouches for a signer’s identity when ID is unavailable, where law permits.',

            'long' => 'A **credible witness** may identify a signer to the notary under strict rules—usually personally known to both notary and signer, and not benefiting from the document.',

        ],

        'personal knowledge' => [

            'short' => 'Identifying a signer because the notary already knows them, without relying on ID documents.',

            'long' => '**Personal knowledge** identification means the notary can truthfully swear they know the signer’s identity from prior acquaintance, where permitted by law.',

        ],

        'credible identifying witness' => [

            'short' => 'A witness used to identify a signer when acceptable ID is not available.',

            'long' => 'A **credible identifying witness** appears with the signer and confirms identity to the notary under rules set by the governing jurisdiction.',

        ],

        'notarial journal' => [

            'short' => 'A chronological record of notarial acts kept by the notary.',

            'long' => 'A **notarial journal** (register) logs each act: date, type, document, signer identity method, and fees. It is evidence if an act is later challenged.',

        ],

        'venue' => [

            'short' => 'The county, state, or jurisdiction stated in a notarial certificate where the act was performed.',

            'long' => 'The **venue** in a notarial certificate identifies where the notary was commissioned and where the act took place—important for validity and recording.',

        ],

        'protest' => [

            'short' => 'A notarial act formally noting dishonor of a negotiable instrument such as a bill of exchange.',

            'long' => 'A **protest** is a certified statement by a notary that a negotiable instrument was presented and refused payment or acceptance, preserving rights of the holder.',

        ],

        'bill of exchange' => [

            'short' => 'A written order to pay a sum of money, which may be protested if dishonored.',

            'long' => 'A **bill of exchange** is a negotiable instrument ordering payment. Notaries may note or protest dishonor under applicable commercial law.',

        ],

        'promissory note' => [

            'short' => 'A written promise to pay a specified sum, often notarized or acknowledged.',

            'long' => 'A **promissory note** is an IOU. Notaries may acknowledge signatures or protest dishonor depending on jurisdiction and instrument type.',

        ],

        'indemnity' => [

            'short' => 'A contract in which one party agrees to compensate another for specified loss or liability.',

            'long' => 'An **indemnity** or indemnity bond shifts risk between parties. Notaries may acknowledge indemnity deeds or affidavits supporting claims.',

        ],

        'affidavit of identity' => [

            'short' => 'A sworn statement confirming a person’s identity, often used when records are lost.',

            'long' => 'An **affidavit of identity** is a sworn declaration establishing who someone is. It is commonly notarized with a jurat.',

        ],

        'affidavit of support' => [

            'short' => 'A sworn statement promising financial support, often for immigration matters.',

            'long' => 'An **affidavit of support** commits a sponsor to support an applicant financially. Notaries typically administer the oath and notarize the signature.',

        ],

        'affidavit of heirship' => [

            'short' => 'A sworn statement identifying heirs of a deceased person.',

            'long' => 'An **affidavit of heirship** helps establish heirs when no probate was opened. Notarization with a jurat is standard.',

        ],

        'subpoena' => [

            'short' => 'A legal order requiring testimony or production of evidence.',

            'long' => 'A **subpoena** compels appearance or documents. Notaries may administer oaths to subpoenaed witnesses or notarize related affidavits.',

        ],

        'deposition' => [

            'short' => 'Out-of-court sworn testimony, often transcribed and sometimes taken before a notary or officer.',

            'long' => 'A **deposition** records sworn testimony for litigation. In some jurisdictions notaries may swear in deponents or notarize deposition certificates.',

        ],

        'procuration' => [

            'short' => 'Authority given to another to act on one’s behalf; related to power of attorney in civil-law systems.',

            'long' => '**Procuration** is a mandate authorizing an agent to act for a principal, often formalized in a notarized instrument in civil-law practice.',

        ],

        'mandate' => [

            'short' => 'A written authority for someone to act for another, similar to a power of attorney.',

            'long' => 'A **mandate** grants representative authority. Notaries authenticate mandates for banking, property, and cross-border matters.',

        ],

        'capacity' => [

            'short' => 'A signer’s legal ability to understand and execute a document.',

            'long' => '**Capacity** means the signer understands the nature and effect of the act. Notaries watch for incapacity, undue influence, and representative authority.',

        ],

        'representative capacity' => [

            'short' => 'Signing on behalf of another entity or person—as director, attorney-in-fact, executor, etc.',

            'long' => '**Representative capacity** requires the notary to confirm both signer identity and authority (corporate resolution, POA, probate grant, etc.).',

        ],

        'corporate resolution' => [

            'short' => 'A company decision authorizing an officer to sign or act, often notarized for third-party reliance.',

            'long' => 'A **corporate resolution** records board or shareholder approval. Banks and registries often require notarized copies before accepting corporate signatures.',

        ],

        'loan signing' => [

            'short' => 'A closing meeting where borrowers sign mortgage and related documents before a notary.',

            'long' => '**Loan signing** (closing) involves numerous documents requiring acknowledgment or jurat. Signing agents guide signers and perform notarial acts.',

        ],

        'e-notarization' => [

            'short' => 'Performing notarial acts electronically using approved platforms and identity proofing.',

            'long' => '**E-notarization** (remote online notarization where allowed) uses audio-video technology and digital seals. Rules vary by jurisdiction.',

        ],

        'ron' => [

            'short' => 'Remote Online Notarization—notarial acts performed via live audio-video when permitted by law.',

            'long' => '**RON (Remote Online Notarization)** allows a notary and signer to be in different locations using approved identity verification and recording requirements.',

        ],

        'notary bond' => [

            'short' => 'A surety bond protecting the public from notary misconduct or errors.',

            'long' => 'A **notary bond** is insurance for the public; it does not protect the notary from liability for negligent or fraudulent acts.',

        ],

        'errors and omissions insurance' => [

            'short' => 'Professional liability insurance covering notary mistakes.',

            'long' => '**E&O insurance** for notaries covers claims arising from negligent errors in notarial acts, supplementing the mandatory bond where required.',

        ],

    ];



    /** @var array<string, string> */

    private const ALIASES = [

        'poa' => 'power of attorney',

        'p.o.a.' => 'power of attorney',

        'p.o.a' => 'power of attorney',

        'attorney in fact' => 'power of attorney',

        'attorney-in-fact' => 'power of attorney',

        'acknowledgement' => 'acknowledgment',

        'notary' => 'notary public',

        'notaris' => 'notary public',

        'notarisation' => 'authentication',

        'notarization' => 'authentication',

        'certify copy' => 'certified copy',

        'true copy' => 'certified copy',

        'sworn affidavit' => 'affidavit',

        'stat dec' => 'statutory declaration',

        'statdec' => 'statutory declaration',

        'remote online notarization' => 'ron',

        'remote notarization' => 'ron',

        'e notarization' => 'e-notarization',

        'enotarization' => 'e-notarization',

        'letters of admin' => 'letters of administration',

        'grant probate' => 'grant of probate',

        'hague apostille' => 'apostille',

        'apostille convention' => 'hague convention',

        'bill exchange' => 'bill of exchange',

        'signing agent' => 'signing agent',

        'loan closer' => 'loan signing',

        'closing agent' => 'loan signing',

        'journal' => 'notarial journal',

        'register' => 'notarial journal',

        'seal' => 'notarial seal',

        'stamp' => 'notarial seal',

        'certificate' => 'notarial certificate',

        'e&o' => 'errors and omissions insurance',

        'eo insurance' => 'errors and omissions insurance',

    ];



    public static function looksLikeDefinitionQuery(string $message): bool

    {

        $lower = strtolower(trim($message));

        if ($lower === '') {

            return false;

        }



        if (AssistantCalculations::looksLikeCalculationQuery($message)) {
            return false;
        }

        if (self::matchDefinitionTerm($message) !== null) {

            return true;

        }



        $hasDefinitionCue = (bool) preg_match(

            '/\b(what is|what are|what\'s|whats|define|definition|definitions|explain|meaning|means|mean|difference between|tell me about|describe|overview of)\b/i',

            $message

        );



        $hasNotaryContext = (bool) preg_match(

            '/\b(jurat|acknowledg|affidavit|apostille|deed|notary|notaris|poa|power of attorney|attestation|oath|affirmation|witness|seal|certificate|authentication|legalis|certified copy|copy certif|protest|statutory declaration|sworn|conveyance|mortgage|testament|probate|executor|mandate|procuration|signing agent|ron|e-notari|notarial|commissioner for oaths|indemnity|promissory|bill of exchange|letters of administration|grant of probate|corporate resolution|capacity|venue|bond)\b/i',

            $message

        );



        if ($hasDefinitionCue && $hasNotaryContext) {

            return true;

        }



        if (preg_match('/\b(poa|jurat|apostille|affidavit|notary)\b.*\b(meaning|definition|mean)\b/i', $message)) {

            return true;

        }



        if (preg_match('/\b(meaning|definition|mean)\b.*\b(poa|jurat|apostille|affidavit|notary|power of attorney)\b/i', $message)) {

            return true;

        }



        return (bool) preg_match('/^(what is|what are|define|explain)\s+.+/i', $lower);

    }



    /** @return array{content: string} */

    public static function handle(string $topic, string $message): array

    {

        return match ($topic) {

            'calculation' => AssistantCalculations::handle($message),

            'definition' => self::definition($message),

            'system_qa' => self::systemQa($message),

            default => self::definition($message),

        };

    }



    /** @return array{content: string} */

    private static function definition(string $message): array

    {

        $term = self::matchDefinitionTerm($message);

        $wantsLong = (bool) preg_match('/\b(detailed|comprehensive|long|full|explain in detail)\b/i', $message);



        if ($term !== null) {

            $entry = self::DEFINITIONS[$term];

            $label = self::formatTermLabel($term);



            return [

                'content' => $wantsLong ? $entry['long'] : ('**' . $label . '** — ' . $entry['short']),

            ];

        }



        if (OllamaService::isEnabled() && OllamaService::isReachable()) {

            try {

                $style = $wantsLong ? 'a comprehensive legal explanation for practicing notaries' : 'one concise sentence for a notary professional';

                $answer = OllamaService::chat([

                    [

                        'role' => 'system',

                        'content' => 'You are a notary practice expert. Define notarial, legal, and conveyancing terms clearly and accurately. Focus on what a notary public needs to know. If the term is not notary-related, say so briefly.',

                    ],

                    ['role' => 'user', 'content' => "Give {$style}: {$message}"],

                ]);



                if (trim($answer) !== '') {

                    return ['content' => trim($answer)];

                }

            } catch (Throwable) {

                // Fall through to built-in glossary hint.

            }

        }



        return ['content' => self::unknownTermMessage($message)];

    }



    /** @return array{content: string} */

    private static function systemQa(string $message): array

    {

        $guides = [

            'client' => assistantAdminLink('pages/clients.php', 'Clients'),

            'case' => assistantAdminLink('pages/cases.php', 'Cases'),

            'payment' => assistantAdminLink('pages/payments.php', 'Payments'),

            'invoice' => assistantAdminLink('pages/payments.php', 'Payments'),

            'appointment' => assistantAdminLink('pages/appointments.php', 'Appointments'),

            'calendar' => assistantAdminLink('pages/appointments.php', 'Appointments'),

            'notification' => assistantAdminLink('pages/notifications.php', 'Notifications'),

            'setting' => assistantAdminLink('pages/settings.php', 'Settings'),

            'user' => assistantAdminLink('pages/users.php', 'Users'),

            'dashboard' => assistantAdminLink('pages/dashboard.php', 'Dashboard'),

            'assistant' => assistantAdminLink('pages/assistant.php', 'AI Assistant'),

        ];



        foreach ($guides as $keyword => $link) {

            if (preg_match('/\b' . preg_quote($keyword, '/') . 's?\b/i', $message)) {

                return [

                    'content' => "You can manage **{$keyword}s** from {$link} in the sidebar.\n\n"

                        . "Use this assistant for counts, searches, drafts, and calculations without leaving the page.",

                ];

            }

        }



        return [

            'content' => "**Portal navigation**\n\n"

                . '• ' . assistantAdminLink('pages/dashboard.php', 'Dashboard') . " — KPIs and charts\n"

                . '• ' . assistantAdminLink('pages/clients.php', 'Clients') . " — client records\n"

                . '• ' . assistantAdminLink('pages/cases.php', 'Cases') . " — matters and documents\n"

                . '• ' . assistantAdminLink('pages/payments.php', 'Payments') . " — invoices and receipts\n"

                . '• ' . assistantAdminLink('pages/appointments.php', 'Appointments') . " — calendar\n"

                . '• ' . assistantAdminLink('pages/notifications.php', 'Notifications') . " — alerts\n"

                . '• ' . assistantAdminLink('pages/settings.php', 'Settings') . " — branding, email, roles",

        ];

    }



    private static function matchDefinitionTerm(string $message): ?string

    {

        $lower = strtolower($message);



        foreach (self::ALIASES as $alias => $canonical) {

            if (preg_match('/\b' . preg_quote($alias, '/') . '\b/i', $lower)) {

                return $canonical;

            }

        }



        $terms = array_keys(self::DEFINITIONS);

        usort($terms, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));



        foreach ($terms as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $lower)) {
                return $term;
            }
        }



        if (preg_match('/\backnowledg/', $lower)) {

            return 'acknowledgment';

        }



        return null;

    }



    private static function formatTermLabel(string $term): string

    {

        if ($term === 'poa' || $term === 'ron') {

            return strtoupper($term);

        }



        if ($term === 'power of attorney') {

            return 'Power of attorney (POA)';

        }



        return ucwords($term);

    }



    private static function unknownTermMessage(string $message): string

    {

        $samples = ['jurat', 'acknowledgment', 'affidavit', 'apostille', 'power of attorney', 'certified copy', 'probate', 'statutory declaration'];



        return 'I do not have a built-in definition for that term yet. Try asking _what is a jurat?_ or _poa meaning_.'

            . "\n\n**Terms I know include:** " . implode(', ', $samples) . ', and many other notarial concepts.';

    }



    private static function extractNumber(string $message, string $pattern): ?float

    {

        if (!preg_match($pattern, $message, $matches)) {

            return null;

        }



        return (float) str_replace(',', '', $matches[1]);

    }

}


