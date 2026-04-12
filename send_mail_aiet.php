<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Load PHPMailer ──
function loadPHPMailer() {
    $composerAutoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require $composerAutoload;
        return true;
    }
    $dir   = __DIR__ . '/phpmailer/';
    $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
    $allExist = true;
    foreach ($files as $f) { if (!file_exists($dir . $f)) { $allExist = false; break; } }

    if (!$allExist) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $base = 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/';
        foreach ($files as $f) {
            $dest = $dir . $f;
            if (!file_exists($dest)) {
                $c = @file_get_contents($base . $f);
                if ($c) file_put_contents($dest, $c);
            }
        }
    }
    foreach ($files as $f) {
        $p = $dir . $f;
        if (file_exists($p)) require_once $p;
        else return false;
    }
    return true;
}

$phpMailerLoaded = loadPHPMailer();

// ── Sanitise ──
function clean($v) { return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8'); }

// All form fields
$program       = clean($_POST['programSelect']   ?? '');
$spec          = clean($_POST['specialization']  ?? '');
$intakeYear    = clean($_POST['intakeYear']       ?? '');
$intakeSem     = clean($_POST['intakeSem']        ?? '');
$studyMode     = clean($_POST['studyMode']        ?? '');
$referral      = clean($_POST['referralSource']   ?? '');
$title         = clean($_POST['title']            ?? '');
$firstName     = clean($_POST['firstName']        ?? '');
$lastName      = clean($_POST['lastName']         ?? '');
$middleName    = clean($_POST['middleName']        ?? '');
$dob           = clean($_POST['dob']              ?? '');
$gender        = clean($_POST['gender']           ?? '');
$nationality   = clean($_POST['nationality']      ?? '');
$passportNum   = clean($_POST['passportNum']      ?? '');
$country       = clean($_POST['country']          ?? '');
$email         = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone         = clean($_POST['phone']            ?? '');
$whatsapp      = clean($_POST['whatsapp']         ?? '');
$address       = clean($_POST['address']          ?? '');
$highestQual   = clean($_POST['highestQual']      ?? '');
$fieldStudy    = clean($_POST['fieldOfStudy']     ?? '');
$institution   = clean($_POST['institution1']     ?? '');
$instCountry   = clean($_POST['instCountry1']     ?? '');
$gradYear      = clean($_POST['gradYear']         ?? '');
$grade         = clean($_POST['grade']            ?? '');
$instrLang     = clean($_POST['instrLang']        ?? '');
$qual2         = clean($_POST['qual2']            ?? '');
$body2         = clean($_POST['body2']            ?? '');
$englishMethod = clean($_POST['englishMethod']    ?? '');
$englishScore  = clean($_POST['englishScore']     ?? '');
$englishDate   = clean($_POST['englishDate']      ?? '');
$jobTitle      = clean($_POST['jobTitle']         ?? '');
$employer      = clean($_POST['employer']         ?? '');
$industry      = clean($_POST['industry']         ?? '');
$yearsExp      = clean($_POST['yearsExp']         ?? '');
$mgmtExp       = clean($_POST['mgmtExp']          ?? '');
$motivation    = clean($_POST['motivation']       ?? '');
$goals         = clean($_POST['goals']            ?? '');
$ref1name      = clean($_POST['ref1name']         ?? '');
$ref1email     = clean($_POST['ref1email']        ?? '');
$ref1title     = clean($_POST['ref1title']        ?? '');
$ref1org       = clean($_POST['ref1org']          ?? '');
$ref           = clean($_POST['ref']              ?? '');

if (!$firstName || !$lastName || !$email || !$program || !$ref) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// ── Upload directory ──
$uploadDir = __DIR__ . '/uploads/applications/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function handleUpload($fieldName, $uploadDir, $ref, $maxBytes, $allowedExts) {
    if (empty($_FILES[$fieldName]['name'])) return ['ok' => false, 'path' => null, 'origName' => null];
    $file    = $_FILES[$fieldName];
    $ext     = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    $newName = $ref . '_' . $fieldName . '.' . $ext;
    $dest    = $uploadDir . $newName;
    if ($file['error'] !== UPLOAD_ERR_OK)     return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    if ($file['size'] > $maxBytes)            return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    if (!in_array($ext, $allowedExts))        return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    return ['ok' => true, 'path' => $dest, 'name' => $newName, 'origName' => $file['name']];
}

$imgExts = ['pdf','jpg','jpeg','png'];
$docExts = ['pdf','doc','docx'];

$uploads = [
    'upload_id'          => handleUpload('upload_id',          $uploadDir, $ref, 5*1024*1024, $imgExts),
    'upload_transcripts' => handleUpload('upload_transcripts', $uploadDir, $ref, 5*1024*1024, $imgExts),
    'upload_cv'          => handleUpload('upload_cv',          $uploadDir, $ref, 5*1024*1024, $docExts),
    'upload_english'     => handleUpload('upload_english',     $uploadDir, $ref, 5*1024*1024, $imgExts),
    'upload_refs'        => handleUpload('upload_refs',        $uploadDir, $ref, 5*1024*1024, $imgExts),
];

$labels = [
    'upload_id'          => 'Passport / ID',
    'upload_transcripts' => 'Academic Transcripts',
    'upload_cv'          => 'CV / Resume',
    'upload_english'     => 'English Certificate',
    'upload_refs'        => 'Reference Letters',
];

// ── Email content ──
$fullName = trim("$title $firstName $middleName $lastName");
$now      = date('l, d F Y \a\t H:i T');
$intake   = "$intakeSem $intakeYear";
$subject  = "New Application | $program | $firstName $lastName [$ref]";

// ── Image extensions that can be shown inline ──
$inlineImageExts = ['jpg','jpeg','png'];

// ── Build inline CID map for image uploads ──
// key => cid string (used in HTML src="cid:...")
$cidMap = [];
foreach ($uploads as $key => $up) {
    if ($up['ok'] && $up['path'] && file_exists($up['path'])) {
        $ext = strtolower(pathinfo($up['path'], PATHINFO_EXTENSION));
        if (in_array($ext, $inlineImageExts)) {
            $cidMap[$key] = $key . '_' . $ref . '@aiet';
        }
    }
}

// ── Helper: render a document row ──
function docRow($label, $key, $up, $cidMap) {
    if (!$up['ok']) {
        return '<tr>
            <td style="padding:10px 14px;font-size:13px;color:#6B7280;border-bottom:1px solid #F3F4F6;white-space:nowrap;width:180px;">' . htmlspecialchars($label) . '</td>
            <td style="padding:10px 14px;border-bottom:1px solid #F3F4F6;">
                <span style="font-size:12px;color:#9CA3AF;background:#F9FAFB;padding:3px 10px;border-radius:4px;border:1px solid #E5E7EB;">Not uploaded</span>
            </td>
        </tr>';
    }
    $imgHtml = '';
    if (isset($cidMap[$key])) {
        $imgHtml = '<br><img src="cid:' . $cidMap[$key] . '" alt="' . htmlspecialchars($label) . '" style="margin-top:10px;max-width:480px;width:100%;border-radius:6px;border:1px solid #E5E7EB;display:block;">';
    }
    return '<tr>
        <td style="padding:10px 14px;font-size:13px;color:#6B7280;border-bottom:1px solid #F3F4F6;white-space:nowrap;vertical-align:top;width:180px;">' . htmlspecialchars($label) . '</td>
        <td style="padding:10px 14px;border-bottom:1px solid #F3F4F6;">
            <span style="font-size:12px;color:#065F46;background:#ECFDF5;padding:3px 10px;border-radius:4px;border:1px solid #A7F3D0;">&#10003; ' . htmlspecialchars($up['origName']) . ' (attached)</span>
            ' . $imgHtml . '
        </td>
    </tr>';
}

// ── HTML email body ──
$body = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F4F6F9;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6F9;padding:32px 0;">
<tr><td align="center">
<table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;">

  <!-- HEADER -->
  <tr><td style="background:#111A42;border-radius:12px 12px 0 0;padding:28px 32px;text-align:center;">
    <div style="font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#1A9B8C;margin-bottom:6px;">American Institute of Education and Training</div>
    <div style="font-size:22px;font-weight:700;color:#FFFFFF;margin-bottom:4px;">New Student Application</div>
    <div style="font-size:13px;color:rgba(255,255,255,0.55);">Submitted ' . $now . '</div>
  </td></tr>

  <!-- REF BANNER -->
  <tr><td style="background:#1A9B8C;padding:12px 32px;text-align:center;">
    <span style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.7);">Reference Number &nbsp;|&nbsp; </span>
    <span style="font-size:14px;font-weight:700;color:#FFFFFF;letter-spacing:1px;">' . $ref . '</span>
  </td></tr>

  <!-- BODY CARD -->
  <tr><td style="background:#FFFFFF;padding:32px;">

    <!-- SECTION 1 -->
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#1A9B8C;border-bottom:2px solid #E8F7F5;padding-bottom:8px;margin-bottom:16px;">1 &mdash; Program Selection</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;width:200px;">Programme Type</td><td style="padding:6px 0;font-size:13px;color:#111827;font-weight:600;">' . htmlspecialchars($program) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Course / Specialism</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($spec ?: 'Not specified') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Intake</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($intake) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Study Mode</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($studyMode) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Referral Source</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($referral ?: 'Not provided') . '</td></tr>
    </table>

    <!-- SECTION 2 -->
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#1A9B8C;border-bottom:2px solid #E8F7F5;padding-bottom:8px;margin-bottom:16px;">2 &mdash; Personal Information</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;width:200px;">Full Name</td><td style="padding:6px 0;font-size:14px;color:#111827;font-weight:700;">' . htmlspecialchars($fullName) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Date of Birth</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($dob) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Gender</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($gender) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Nationality</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($nationality) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">ID / Passport No.</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($passportNum) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Country of Residence</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($country) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Email Address</td><td style="padding:6px 0;font-size:13px;color:#1A9B8C;">' . htmlspecialchars($email) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Phone Number</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($phone) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">WhatsApp</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($whatsapp ?: 'Same as phone') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Mailing Address</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($address) . '</td></tr>
    </table>

    <!-- SECTION 3 -->
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#1A9B8C;border-bottom:2px solid #E8F7F5;padding-bottom:8px;margin-bottom:16px;">3 &mdash; Academic Background</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;width:200px;">Highest Qualification</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($highestQual) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Field of Study</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($fieldStudy) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Institution</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($institution) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Country of Institution</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($instCountry) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Year of Graduation</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($gradYear) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Grade / GPA</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($grade ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Language of Instruction</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($instrLang) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Additional Qualification</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($qual2 ?: 'None') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Awarding Body</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($body2 ?: 'N/A') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">English Proficiency</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($englishMethod) . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">English Test Score</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($englishScore ?: 'N/A') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">English Test Date</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($englishDate ?: 'N/A') . '</td></tr>
    </table>

    <!-- SECTION 4 -->
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#1A9B8C;border-bottom:2px solid #E8F7F5;padding-bottom:8px;margin-bottom:16px;">4 &mdash; Professional Experience &amp; Goals</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;width:200px;">Current Job Title</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($jobTitle ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Current Employer</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($employer ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Industry / Sector</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($industry ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Years of Experience</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($yearsExp ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Years in Management</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($mgmtExp ?: 'Not provided') . '</td></tr>
    </table>
    <div style="background:#F9FAFB;border-left:3px solid #1A9B8C;border-radius:0 6px 6px 0;padding:14px 16px;margin-bottom:12px;">
      <div style="font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#1A9B8C;margin-bottom:6px;">Statement of Purpose</div>
      <div style="font-size:13px;color:#374151;line-height:1.7;">' . nl2br(htmlspecialchars($motivation ?: 'Not provided')) . '</div>
    </div>
    <div style="background:#F9FAFB;border-left:3px solid #1A9B8C;border-radius:0 6px 6px 0;padding:14px 16px;margin-bottom:28px;">
      <div style="font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#1A9B8C;margin-bottom:6px;">Career Goals</div>
      <div style="font-size:13px;color:#374151;line-height:1.7;">' . nl2br(htmlspecialchars($goals ?: 'Not provided')) . '</div>
    </div>

    <!-- SECTION 5 -->
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#1A9B8C;border-bottom:2px solid #E8F7F5;padding-bottom:8px;margin-bottom:16px;">5 &mdash; Reference</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;width:200px;">Name</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($ref1name ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Email</td><td style="padding:6px 0;font-size:13px;color:#1A9B8C;">' . htmlspecialchars($ref1email ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Position</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($ref1title ?: 'Not provided') . '</td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#6B7280;">Organisation</td><td style="padding:6px 0;font-size:13px;color:#111827;">' . htmlspecialchars($ref1org ?: 'Not provided') . '</td></tr>
    </table>

    <!-- SECTION 6 -->
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#1A9B8C;border-bottom:2px solid #E8F7F5;padding-bottom:8px;margin-bottom:16px;">6 &mdash; Uploaded Documents</div>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;margin-bottom:28px;">';

foreach ($uploads as $key => $up) {
    $label = $labels[$key] ?? $key;
    $body .= docRow($label, $key, $up, $cidMap);
}

$body .= '
    </table>

  </td></tr>

  <!-- FOOTER -->
  <tr><td style="background:#111A42;border-radius:0 0 12px 12px;padding:20px 32px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="font-size:12px;color:rgba(255,255,255,0.45);">Files saved on server: <span style="color:rgba(255,255,255,0.7);">uploads/applications/</span></td>
        <td align="right" style="font-size:12px;color:rgba(255,255,255,0.45);">Reply-To: <a href="mailto:' . $email . '" style="color:#1A9B8C;text-decoration:none;">' . $email . '</a></td>
      </tr>
    </table>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>';

// ── Plain-text fallback ──
$altBody  = "AIET APPLICATION — {$ref}\r\n";
$altBody .= "Submitted: {$now}\r\n\r\n";
$altBody .= "Applicant : {$fullName}\r\nEmail     : {$email}\r\nPhone     : {$phone}\r\n\r\n";
$altBody .= "Programme : {$program}\r\nIntake    : {$intake}\r\nMode      : {$studyMode}\r\n\r\n";
$altBody .= "Please view this email in an HTML-capable client for full details and document previews.\r\n";

// ── Send email ──
$sent    = false;
$warning = '';

if ($phpMailerLoaded) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSendmail();
        $mail->setFrom('noreply@aietglobal.us', 'AIET Admissions Portal');
        $mail->addAddress('info@aietglobal.us', 'AIET Admissions');
        $mail->addReplyTo($email, $fullName);
        $mail->Subject  = $subject;
        $mail->isHTML(true);
        $mail->Body     = $body;
        $mail->AltBody  = $altBody;

        // Embed image uploads inline, attach everything else
        foreach ($uploads as $key => $up) {
            if (!$up['ok'] || !$up['path'] || !file_exists($up['path'])) continue;
            if (isset($cidMap[$key])) {
                // Inline image — embedded with CID so it shows in the email body
                $mail->addEmbeddedImage($up['path'], $cidMap[$key], $up['origName']);
            } else {
                // PDF / Word doc — regular attachment
                $mail->addAttachment($up['path'], $up['origName']);
            }
        }

        $mail->send();
        $sent = true;

    } catch (\Exception $e) {
        $warning         = 'PHPMailer: ' . $e->getMessage();
        $phpMailerLoaded = false;
    }
}

if (!$sent) {
    // Fallback: basic mail() without attachments
    $headers  = "From: noreply@aietglobal.us\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $fallbackNote = "\r\n\r\nNOTE: File attachments failed. Retrieve from server: uploads/applications/\r\n" . ($warning ? "Error: $warning" : '');
    $sent = mail('info@aietglobal.us', $subject, $altBody . $fallbackNote, $headers);
}

echo json_encode([
    'success' => $sent,
    'ref'     => $ref,
    'warning' => $warning ?: null
]);
?>