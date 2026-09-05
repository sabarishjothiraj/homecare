{{--
    Tamil service agreement, modelled on the printed Sri Muruga Home Nursing form.

    Rendered by TamilPdfService (mPDF) — NOT DomPDF, which cannot shape Tamil.
    All measurements are in mm/pt because mPDF resolves those most predictably.

    $contract may be null, in which case the document prints as a blank form.
--}}
@php
    $dots = str_repeat('.', 60);
    $line = fn ($value, $len = 40) => $value ?: str_repeat('.', $len);
    $patientName = $contract?->patient?->name;
    $items = $contract?->items ?? collect();
    $assignments = $contract?->assignments->where('is_active', true) ?? collect();
    $firstAssignment = $assignments->first();
    $employee = $firstAssignment?->employee;
@endphp
<style>
    @page { margin: 6mm; }
    body {
        font-family: hindmadurai;
        font-size: 8.2pt;
        line-height: 1.45;
        color: #111;
    }
    .sheet {
        border: 2.2mm solid #F4E04D;
        padding: 1.6mm;
        background: #FFFDF0;
    }
    .inner {
        border: 0.6mm solid #0F7A3D;
        background: #FFFFFF;
        padding: 3mm 3.5mm 2mm 3.5mm;
    }

    /* ---------- header ---------- */
    .brand-table { width: 100%; border-collapse: collapse; }
    .brand-table td { vertical-align: middle; }
    .logo-cell { width: 22mm; text-align: center; }
    .logo-badge {
        width: 19mm; height: 19mm;
        border: 0.7mm solid #0F7A3D;
        border-radius: 10mm;
        background: #FFFFFF;
        color: #0F7A3D;
        font-size: 6pt;
        font-weight: bold;
        text-align: center;
        padding-top: 4.6mm;
    }
    .reg-badge {
        margin-top: 1mm;
        font-size: 5.4pt;
        color: #0F7A3D;
        font-weight: bold;
    }
    .name-cell { text-align: center; }
    .name-ta {
        font-size: 20pt;
        font-weight: bold;
        color: #C62828;
        line-height: 1.15;
    }
    .name-en {
        font-size: 20pt;
        font-weight: bold;
        color: #0F5FA8;
        letter-spacing: 0.3pt;
        line-height: 1.1;
    }
    .tagline {
        font-size: 7.6pt;
        font-weight: bold;
        color: #222;
        letter-spacing: 0.2pt;
    }
    .services-bar {
        background: #0F7A3D;
        color: #FFFFFF;
        font-size: 8pt;
        font-weight: bold;
        text-align: center;
        padding: 1.1mm 0;
        margin-top: 1.2mm;
    }
    .contact {
        text-align: center;
        font-size: 7.6pt;
        font-weight: bold;
        margin-top: 1.6mm;
        line-height: 1.5;
    }
    .contact .addr { color: #111; }
    .contact .phone { color: #C62828; }
    .contact .web { color: #0F5FA8; }
    .support-bar {
        background: #C62828;
        color: #FFFFFF;
        font-size: 8.6pt;
        font-weight: bold;
        text-align: center;
        padding: 1.1mm 0;
        margin: 1.6mm auto 0 auto;
        width: 62%;
        border-radius: 1mm;
    }
    .section-bar {
        background: #0F7A3D;
        color: #FFFFFF;
        font-size: 11pt;
        font-weight: bold;
        text-align: center;
        padding: 1.6mm 0;
        margin: 2.4mm 0 2.6mm 0;
        border-radius: 1mm;
    }

    /* ---------- meta ---------- */
    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
    .meta-table td { font-size: 9pt; font-weight: bold; padding-bottom: 1mm; }

    /* ---------- terms ---------- */
    .terms { width: 100%; border-collapse: collapse; }
    .terms td { vertical-align: top; padding-bottom: 1.5mm; }
    .terms .num { width: 6mm; text-align: right; padding-right: 1.6mm; }
    .terms .body { text-align: justify; }

    .sign-row { width: 100%; border-collapse: collapse; margin-top: 5mm; }
    .sign-row td { font-size: 8pt; color: #555; padding-top: 6mm; }
    .sign-row .right { text-align: right; }

    .footnote {
        background: #F4E04D;
        color: #0F7A3D;
        font-size: 7.6pt;
        font-weight: bold;
        text-align: center;
        padding: 1mm 0;
        margin-top: 2mm;
    }

    /* ---------- page 2 ---------- */
    .party { font-size: 10pt; margin-top: 3mm; }
    .party .label { color: #111; }
    .party .value { color: #666; }
    .detail-table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    .detail-table td { font-size: 8.6pt; padding: 1.1mm 0; vertical-align: bottom; }
    .detail-table .k { width: 46mm; }
    .detail-table .c { width: 3mm; }
    .detail-table .v { border-bottom: 0.25mm dotted #444; }
    .note-red {
        color: #C62828;
        font-size: 8pt;
        font-weight: bold;
        text-align: center;
        margin: 2.5mm 0 1.5mm 0;
    }
    .small-note { font-size: 7.4pt; color: #333; margin-top: 1.5mm; }
    .fill { border-bottom: 0.25mm dotted #444; }
</style>

<div class="sheet">
<div class="inner">

    {{-- ================= HEADER ================= --}}
    <table class="brand-table">
        <tr>
            <td class="logo-cell">
                <div class="logo-badge">
                    ஸ்ரீ முருகா<br>SINCE 2009
                </div>
                <div class="reg-badge">Govt.Reg: {{ $company_reg_no ?? '851/2015' }}</div>
            </td>
            <td class="name-cell">
                <div class="name-ta">ஸ்ரீ முருகா ஹோம் நர்சிங்</div>
                <div class="name-en">{{ strtoupper($company_name ?? 'SRI MURUGA HOME NURSING') }}</div>
                <div class="tagline">COMPLETE HEALTH CARE SERVICES @ YOUR DOORSTEP</div>
                <div class="services-bar">HOME CARE, HOME NURSE, HOME SERVANT &amp; MAID</div>
            </td>
        </tr>
    </table>

    <div class="contact">
        <div class="addr">{{ $company_address ?? 'No 1/2, Sathy Road, Near G.P. Hospital, Gandhipuram, Coimbatore - 641012' }}</div>
        <div class="phone">{{ $company_phone ?? '+91 999 481 4747  |  +91 8098 199 199  |  +91 999 487 5050' }}
            <span class="web">| {{ $company_email ?? 'srimurugahomecare@gmail.com' }}</span>
        </div>
        <div class="web">www.srimurugahomenursing.com  |  www.srimurugahomenursing.in</div>
    </div>

    <div class="support-bar">24/7 CUSTOMER SUPPORT : (+91) 8098 199 199</div>

    <div class="section-bar">நர்ஸ்களை கூட்டிச் செல்வதற்கான நிபந்தனைகள்</div>

    <table class="meta-table">
        <tr>
            <td width="50%">தேதி : {{ $contract?->created_at?->format('d/m/Y') ?: '.....................' }}</td>
            <td width="50%">ஒப்பந்த எண் : {{ $contract?->contract_number ?: '.....................' }}</td>
        </tr>
    </table>

    {{-- ================= TERMS 1-14 ================= --}}
    <table class="terms">
        <tr>
            <td class="num">1.</td>
            <td class="body">தங்கள் இல்லங்களுக்கு அனுப்பும் ஹோம் நர்ஸ்கள் அவர்களின் திறமை, நன்னடத்தை, அவர்களை பற்றிய முழு விபரங்களை அறிந்த பின்னே அனுப்பி வைப்போம்.</td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td class="body">தங்கள் இல்லத்திற்கு வரும் வேலையாட்களுக்கு முழு பாதுகாப்பு தாங்கள் தான் தர வேண்டும். நர்ஸ்கள் நோயாளிக்கு உண்டான வேலையை மட்டும் செய்வார்கள் மற்றபடி வீட்டு வேலையையோ அதிகப்படியான பொறுப்புகளையோ கொடுக்கக் கூடாது. பணம், நகைகளை வாடிக்கையாளர் பாதுகாப்பிலேயே வைத்துக் கொள்ள வேண்டும். வேலையாட்கள் பாதுகாப்பில் கொடுத்து அதனால் பொருட்சேதமோ, பணச்சேதமோ ஏற்பட்டால் அதற்கு எங்கள் நிறுவனம் பொறுப்பாகாது.</td>
        </tr>
        <tr>
            <td class="num">3.</td>
            <td class="body">தங்கள் இல்லத்தில் தங்கி இருக்கும் வேலையாட்களுக்கு இரண்டு நாள் விடுமுறையும் தினமும் வந்து செல்வோருக்கு ஞாயிறு தோறும் விடுமுறையும் தர வேண்டும்.</td>
        </tr>
        <tr>
            <td class="num">4.</td>
            <td class="body">தாங்களுக்கு வேலையாட்கள் தேவையெனில் பதிவு கட்டணம் ரூ. {{ $contract ? number_format((float) $contract->amount, 0) : '.......................' }} – ம் ஒரு மாத சம்பளத்தொகையும் முன் பணமாக செலுத்த வேண்டும், தாங்கள் கட்டும் பதிவுக்கட்டணம் திருப்பித் தரப்படமாட்டாது. அட்வான்சாக செலுத்தும் ஒரு மாத சம்பளம் சர்வீஸ் தொகையில் மாதம் ரூ. ....................... பிடித்துக் கொண்டு மீதி சம்பளம் ரூ. ....................... மாத மாதம் வேலையாட்கள் வசம் கொடுக்க வேண்டும்.</td>
        </tr>
        <tr>
            <td class="num">5.</td>
            <td class="body">நாங்கள் கொடுக்கும் நர்ஸ்கள் தங்களுக்கு பிடிக்கவில்லை என்றாலோ நர்ஸ் தங்களிடம் வேலை செய்ய விரும்பவில்லை என்றாலோ 5 (ஐந்து) நர்ஸ்கள் வரை மாற்றி அனுப்பப்படும். 5 நர்ஸ்களும் அங்கு வேலை செய்ய முடியவில்லை எனில் அதற்கு மேல் தங்களுக்கு நர்ஸ்கள் அனுப்பப்படமாட்டாது. தாங்கள் கட்டிய தொகையை 5 நர்ஸ் செலவுத் தொகையாக கணக்கிட்டு கழித்துக் கொள்ளப்படும்.</td>
        </tr>
        <tr>
            <td class="num">6.</td>
            <td class="body">நர்ஸ்களை தாங்கள் பணம் கட்டி கூட்டிச் சென்று ஒரு வாரத்திலோ அல்லது ஒரு சில தினங்களிலோ நோயாளிக்கு குணமாகிவிட்டாலோ அல்லது இறந்து விட்டாலோ கட்டிய தொகைக்கு திரும்பத் தரப்பட முடியாது.</td>
        </tr>
        <tr>
            <td class="num">7.</td>
            <td class="body">தாங்கள் எத்தனை மாதங்கள் சர்வீஸ் தேவையோ அதுவரை நர்ஸ்களை மட்டும் அனுப்பி வைப்போம். அதற்கு இடையில் பணம் கேட்டால் திருப்பித் தரப்படமாட்டாது. நர்ஸை அழைத்து செல்லும் வீட்டில் கண்டிப்பாக பெண்கள் இருக்க வேண்டும்.</td>
        </tr>
        <tr>
            <td class="num">8.</td>
            <td class="body">நர்ஸ்களுக்கு ஒரு மாதத்திற்கு மட்டும் நர்ஸ் தேவையென்றால் 28 நாட்கள் மட்டுமே பணி நாட்கள் மாதம் 2 நாள் விடுமுறை, இந்த ஒரு மாதத்திற்கிடையில் நர்ஸ் தேவையில்லையெனில் நர்ஸை தாங்கள் கூட்டு வந்து ஆஃபீஸில் விடவும், பணம் திருப்பித் தரப்படமாட்டாது.</td>
        </tr>
        <tr>
            <td class="num">9.</td>
            <td class="body">தாங்கள் கூட்டிக் கொண்டு போகும் நர்ஸ்கள் தேவையில்லை என்றால் தாங்களே கூட்டி வந்து அலுவலகத்தில் ஒப்படைத்து அதற்கான சான்றிதழை பெற்றுக் கொள்ளாமல் விட்டால் அனுப்பப்படும். அதை விடுத்து தங்களுக்கு அவர்களை நீங்களே அனுப்பி விட்டு அவர்கள் எங்களது சென்றுவிட்டாலோ அவர்களுக்கு ஏதேனும் விபரீதம், அசம்பாவிதம் ஏற்பட்டாலோ அதற்கு தாங்களே முழு பொறுப்பும் ஏற்க வேண்டும்.</td>
        </tr>
        <tr>
            <td class="num">10.</td>
            <td class="body">நர்ஸ்களை கூட்டிச்செல்பவர்கள் தங்களின் ரேசன் கார்டு ஜெராக்ஸ், வாக்காளர் அடையாள அட்டையின் ஜெராக்ஸ், ஆதார் அட்டை ஜெராக்ஸ், மேலும் இரண்டு புகைப்படங்கள் கொடுத்த பின்னரே கூட்டிச்செல்ல வேண்டும்.</td>
        </tr>
        <tr>
            <td class="num">11.</td>
            <td class="body">அனைத்து பரிவர்த்தனைகளும் பொருந்தக் கூடிய சேவை வரிகள் முதலியன இந்திய நாணயத்தில் இருக்கும்.</td>
        </tr>
        <tr>
            <td class="num">12.</td>
            <td class="body">இந்த சேவைகளைப் பெறுவதற்காக நீங்கள் மற்றும் உங்கள் குடும்ப உறுப்பினரால் வழங்கப்பட்ட அனைத்து தகவல்களும் உண்மை என்று உத்திரவாதம் அளிக்கிறீர்கள்.</td>
        </tr>
        <tr>
            <td class="num">13.</td>
            <td class="body">தங்களுக்கு நர்ஸ்களை மாற்ற வேண்டி இருப்பின் தாங்கள் தகவல் தெரிவித்த நாளிலிருந்து 3 முதல் 5 வேலை நாட்களுக்குள் மாற்றித் தரப்படும்.</td>
        </tr>
        <tr>
            <td class="num">14.</td>
            <td class="body">முக்கிய விஷேச நாட்களில் <b>(Festival Days)</b> வேலையாட்களுக்கு விடுமுறை அளிக்க வேண்டும். அந்த நாட்களுக்கு ஆட்களை மாற்றித் தருவது குறித்து நிர்வாகத்தின் முடிவே இறுதியானது.</td>
        </tr>
    </table>

    <table class="sign-row">
        <tr>
            <td width="50%">Signature of Customer</td>
            <td width="50%" class="right">Signature of Sri Muruga Home Nursing</td>
        </tr>
    </table>

</div>
<div class="footnote">குறிப்பு : இந்நிறுவனம் மீது ஏற்படும் சட்ட திட்டங்கள் அனைத்தும் கோவை நீதிமன்ற வரைமுறைக்குட்பட்டது</div>
</div>

<pagebreak />

{{-- ================= PAGE 2 ================= --}}
<div class="sheet">
<div class="inner">

    <table class="terms">
        <tr>
            <td class="num">15.</td>
            <td class="body">ஸ்ரீ முருகா ஹோம் நர்சிங் நிறுவனம் வாடிக்கையாளரின் தேவைகளை மிகவும் திறமையாக பூர்த்தி செய்ய அனைத்து சிறந்த வழிகளையும் முயற்சிகளையும் இந்திய சட்டத்திற்குட்பட்டு பயன்படுத்தும் என்று வாடிக்கையாளருக்கு உறுதியளிக்கிறது.</td>
        </tr>
        <tr>
            <td class="num">16.</td>
            <td class="body">எங்கள் சேவைகள் / பணிகளில் நீங்கள் மகிழ்ச்சியடையவில்லை என்றால் எங்கள் நிறுவனத்திற்கு நேரடியாகவோ, மின்னஞ்சல் <b>(srimurugahomecare@gmail.com)</b> அல்லது தொலைபேசி <b>(+91-8098 199 199)</b> வாயிலாகவோ எங்களை தொடர்பு கொள்ள வேண்டும். அதன்மூலம் தங்களுக்கு ஏற்ற தீர்வு கிடைக்கும். அதை தவிர்த்து நேரடியாகவோ அல்லது மறைமுகமாகவோ, பொதுவெளியிலோ அல்லது இணையதளத்திலோ எங்கள் நிறுவனத்திற்கெதிராக நிறுவனத்தின் பெயருக்கு களங்கம் விளைவிக்கும் வகையில் எந்த விமர்சனங்களையும் வைக்கக் கூடாது.</td>
        </tr>
        <tr>
            <td class="num">17.</td>
            <td class="body">ஸ்ரீ முருகா ஹோம் நர்சிங் சேவைகள் வாடிக்கையாளருக்கு திருப்தியளிக்கவில்லையென்றால் தாங்கள் செலுத்திய தொகையில் அரசாங்க வரிகள், சேவைக் கட்டணம், இதர செலவுகள் போக மீதமுள்ள தொகை, தாங்கள் நிறுவனத்தின் சேவையை துண்டித்துக் கொண்ட நாளிலிருந்து 20 வேலை நாட்களுக்குள் <b>Prorata basis –</b> ல் மட்டுமே தங்களுக்கு வழங்கப்படும்.</td>
        </tr>
        <tr>
            <td class="num">18.</td>
            <td class="body">வாடிக்கையாளராகிய நான் <b>(Party - 2 {{ $patientName ? e($patientName) : '...........................................' }})</b> எனது சுயவிருப்பத்தோடு மேற்கூறிய அனைத்து நிபந்தனைகளையும் ஏற்று இந்த சேவையை பெற்றுக் கொள்கிறேன். மேலும் மேற்கூறிய அனைத்து நிபந்தனைகளில் ஏதேனும் ஒன்றை மீறும்பட்சத்தில் நிறுவனத்திற்கு ஏற்படும் அனைத்து விளைவுகளுக்கும் நான் பொறுப்பேற்றுக் கொள்கிறேன்.</td>
        </tr>
    </table>

    <div class="party"><b>Party 1 :</b> <span class="label">Sri Muruga Home Nursing</span></div>
    <div class="party"><b>Party 2 :</b> <span class="value">{{ $patientName ?: 'Customer Name' }}</span></div>

    <div class="section-bar">வேலையாட்கள் விபரம்</div>

    <table class="detail-table">
        <tr><td class="k">பெயர்</td><td class="c">:</td><td class="v">{{ $employee ? e(trim($employee->getField('first_name') . ' ' . $employee->getField('last_name'))) : '' }}</td></tr>
        <tr><td class="k">வயது</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
        <tr><td class="k">க.பெயர் /த.பெயர்</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
        <tr><td class="k">தொலைபேசி எண்– 1</td><td class="c">:</td><td class="v">{{ $employee ? e($employee->getField('phone')) : '' }}</td></tr>
        <tr><td class="k">தொலைபேசி எண்– 2</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
        <tr><td class="k">பெயர்</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
        <tr><td class="k">தொலைபேசி</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
        <tr><td class="k">அனுபவம் / செய்ய தெரிந்த வேலை</td><td class="c">:</td><td class="v">{{ $firstAssignment?->service?->name }}</td></tr>
        <tr>
            <td class="k">அடையாள அட்டை</td><td class="c">:</td>
            <td>&#9744; Aadhaar &nbsp;/&nbsp; &#9744; Voter ID &nbsp;/&nbsp; &#9744; DL &nbsp;/&nbsp; &#9744; Ration Card</td>
        </tr>
        <tr><td class="k">அடையாள அட்டை எண்</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
    </table>

    <div class="small-note">
        தாங்கள் கேட்டுக்கொண்டபடி தாங்கள் கூட்டிச் செல்லப்படும் நர்ஸ்கள் செய்ய வேண்டிய வேலைகள், நோயாளியின் நோய் விபரங்கள்
    </div>
    <div class="fill">&nbsp;</div>
    <div class="fill">&nbsp;</div>
    <div class="fill">&nbsp;</div>

    <div class="small-note">
        குறிப்பு : மருத்துவம் மற்றும் மருத்துவம் சார்ந்த தவறுகள், சிகிச்சை தவறுகளுக்கு நிர்வாகமோ, நர்ஸ்களோ பொறுப்பல்ல.<br>
        நிர்வாகம் அனுப்பும் நர்ஸ்களின் அனுபவத்திற்கேற்ற வேலைகளை மட்டும் வழங்குமாறு அறிவுறுத்துகிறேன்.
    </div>

    <div class="note-red">எங்கள் நிறுவனம் மனித உரிமை நிபந்தனைகளுக்கு உட்பட்டது.</div>

    <div class="section-bar">வேலைக்கு நர்ஸ்களை அழைத்து செல்பவரின் உறுதிமொழி</div>

    <div class="small-note">
        தங்களின் நிபந்தனைகளை படித்துப் பார்த்தேன். தங்களின் நிபந்தனைப்படி கூட்டிச் செல்லும் நர்ஸ்
        <span class="fill">{{ $employee ? e(trim($employee->getField('first_name') . ' ' . $employee->getField('last_name'))) : str_repeat('.', 55) }}</span>
        யை நல்ல முறையில் பாதுகாப்பு அளிப்பேன் என்று உறுதி கூறுகிறேன்.
    </div>

    <table class="detail-table" style="margin-top:4mm;">
        <tr><td class="k">முகவரி</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
        <tr><td class="k">&nbsp;</td><td class="c">&nbsp;</td><td class="v">&nbsp;</td></tr>
        <tr><td class="k">செல்</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
    </table>

    <table class="sign-row">
        <tr>
            <td width="50%"><span style="background:#0F7A3D;color:#fff;padding:0.8mm 2mm;font-weight:bold;">வேலைக்கு கூட்டிச் செல்பவரின் கையொப்பம்</span></td>
            <td width="50%" class="right"><span style="background:#0F7A3D;color:#fff;padding:0.8mm 2mm;font-weight:bold;">நிறுவனத்தார் கையொப்பம்</span></td>
        </tr>
        <tr>
            <td colspan="2" style="font-size:6.6pt;color:#444;padding-top:1mm;">
                *கொரியரில் அனுப்பப்படும் ஆவணங்களுக்கு வாடிக்கையாளரின் கையொப்பம் அவசியமில்லை
            </td>
        </tr>
    </table>

</div>
<div class="footnote">குறிப்பு : எங்கள் நிறுவனம் மனித உரிமை ஆணையத்தின் சட்ட திட்டங்களுக்குட்பட்டு செயல்படுகிறது.</div>
</div>
