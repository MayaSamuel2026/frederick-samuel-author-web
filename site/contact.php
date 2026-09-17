<?php
header('Content-Type: text/html; charset=UTF-8');

$to = 'frederick@fredericksamuel.com';
$topics = [
    'representation' => 'Literary representation',
    'rights' => 'Publishing & rights',
    'media' => 'Media & interviews',
    'events' => 'Events & speaking',
    'reader' => 'Reader correspondence',
    'other' => 'Other enquiry',
];

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $honeypot = trim((string)($_POST['website'] ?? ''));

    // Quietly accept obvious bot submissions without sending mail.
    if ($honeypot !== '') {
        $sent = true;
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $topicKey = trim((string)($_POST['topic'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        $name = preg_replace('/[\r\n]+/', ' ', $name);
        $name = mb_substr($name, 0, 120);
        $email = mb_substr($email, 0, 200);
        $message = mb_substr($message, 0, 5000);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '' || !isset($topics[$topicKey])) {
            $error = 'Please check the form fields and try again.';
        } else {
            $topic = $topics[$topicKey];
            $subject = '[fredericksamuel.com] ' . $topic . ' — ' . $name;
            $body = "New enquiry from fredericksamuel.com\n\n"
                  . "Name: {$name}\n"
                  . "Email: {$email}\n"
                  . "Regarding: {$topic}\n\n"
                  . "Message:\n{$message}\n\n"
                  . "Submitted: " . gmdate('Y-m-d H:i:s') . " UTC\n";

            $headers = [
                'From: Frederick Samuel Website <frederick@fredericksamuel.com>',
                'Reply-To: ' . $email,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
            if (!$sent) {
                $error = 'The website could not send your message just now.';
            }
        }
    }
} else {
    header('Location: index.html#contact', true, 303);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<meta name="robots" content="noindex"/>
<title><?php echo $sent ? 'Message sent' : 'Contact'; ?> — Frederick Samuel</title>
<style>
:root{--ink:#0b0d0e;--white:#f7f5ef;--muted:#9b9c98;--line:rgba(255,255,255,.13);--rust:#9c3b31;--serif:"Iowan Old Style","Palatino Linotype",Palatino,Baskerville,Georgia,serif;--sans:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 82% 14%,rgba(156,59,49,.12),transparent 28%),linear-gradient(120deg,#0b0d0e,#111515 60%,#090b0c);color:var(--white);font-family:var(--sans);display:grid;grid-template-rows:auto 1fr}.nav{height:78px;border-bottom:1px solid var(--line);display:flex;align-items:center}.shell{width:min(1180px,88vw);margin:auto}.nav .shell{display:flex;align-items:center;justify-content:space-between}.brand{font:400 20px var(--serif);letter-spacing:.15em;text-decoration:none;color:inherit}.back{font-size:9px;letter-spacing:.15em;text-transform:uppercase;color:#b7bab6;text-decoration:none}.main{display:grid;place-items:center;padding:70px 0 100px}.card{width:min(850px,88vw);border-top:1px solid rgba(255,255,255,.22);padding-top:34px}.eyebrow{font-size:9px;letter-spacing:.23em;text-transform:uppercase;color:#8f9390}.card h1{font:400 clamp(58px,8vw,104px)/.87 var(--serif);letter-spacing:-.035em;margin:24px 0 28px}.card p{font:400 22px/1.55 var(--serif);color:#c3c5c0;max-width:720px}.email{color:#f0ece4;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.3);padding-bottom:4px}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:36px}.btn{min-height:50px;padding:0 19px;display:inline-flex;align-items:center;border:1px solid rgba(255,255,255,.25);color:#fff;text-decoration:none;font-size:9px;letter-spacing:.16em;text-transform:uppercase}.btn.primary{background:#f1ede5;color:#111;border-color:#f1ede5}.error{color:#d7a69f}
</style>
</head>
<body>
<nav class="nav"><div class="shell"><a class="brand" href="index.html">FREDERICK SAMUEL</a><a class="back" href="index.html#contact">← Back to contact</a></div></nav>
<main class="main">
<div class="card">
<div class="eyebrow">Contact · Frederick Samuel</div>
<?php if ($sent): ?>
<h1>Message<br/>received.</h1>
<p>Thank you. Your enquiry has been sent to Frederick Samuel and can be answered directly to the email address you provided.</p>
<div class="actions"><a class="btn primary" href="index.html">Return to the website</a></div>
<?php else: ?>
<h1>Almost<br/>there.</h1>
<p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<p>Please try the form again, or email <a class="email" href="mailto:frederick@fredericksamuel.com">frederick@fredericksamuel.com</a> directly.</p>
<div class="actions"><a class="btn primary" href="index.html#contact">Try again</a><a class="btn" href="mailto:frederick@fredericksamuel.com">Email directly</a></div>
<?php endif; ?>
</div>
</main>
</body>
</html>
