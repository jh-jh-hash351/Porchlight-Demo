<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';

$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Policy - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script src="js/theme.js"></script>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="images/logo.png" alt="Porchlight Logo" style="height: 32px; width: auto; border-radius: 4px;">
                <span>Porchlight</span>
            </a>
            <div class="topbar-actions" style="margin-left: auto;">
                <?php if (!$user): ?>
                    <a href="login.php" class="btn btn-ghost btn-small">Sign In</a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-ghost btn-small">Back to Feed</a>
                <button class="icon-btn theme-toggle" aria-label="Toggle dark mode">
                    <span class="icon-sun"><?= icon('sun') ?></span>
                    <span class="icon-moon"><?= icon('moon') ?></span>
                </button>
            </div>
        </div>
    </header>

    <main class="policy-main">
        <a class="group-back" href="index.php">&larr; Back to feed</a>

        <section class="panel policy-hero">
            <p class="policy-kicker">Community &amp; Legal</p>
            <h1 class="policy-title">Porchlight social media policy</h1>
            <p class="policy-updated">Last updated <?= date('F j, Y') ?></p>
            <p class="policy-lede">
                Porchlight is a neighborhood social space in the Philippines. This policy explains what we expect
                from users online, the conduct we prohibit, and the Philippine laws that guide how we respond to
                reports about cybercrime, harassment, and cyber libel.
            </p>
        </section>

        <nav class="policy-nav" aria-label="Policy sections">
            <a href="#social-media">Social media</a>
            <a href="#cybercrime">Cybercrime</a>
            <a href="#harassment">Harassment</a>
            <a href="#cyber-libel">Cyber libel</a>
            <a href="#enforcement">Enforcement</a>
        </nav>

        <section class="panel policy-section" id="social-media">
            <p class="policy-kicker">01 &middot; Social media</p>
            <h2>Social media in the Philippines</h2>
            <p>
                Social media gives everyone a place to speak, but it does not remove legal responsibility for what
                is posted. The 1987 Constitution protects freedom of expression, yet that right has limits.
                Threats, harassment, fraud, libel, child sexual abuse material, and other unlawful content are not
                protected.
            </p>
            <p>As a Porchlight user, you should:</p>
            <ul>
                <li>Use your own identity and do not impersonate another person or create fake accounts to deceive others.</li>
                <li>Post information you have reason to believe is true. Distinguish opinion from fact and correct mistakes promptly.</li>
                <li>Respect privacy. Do not publish someone's address, phone number, ID, workplace schedule, or private media without consent.</li>
                <li>Respect copyright and other people's creative work.</li>
            </ul>
            <div class="policy-note">
                <strong>Privacy law:</strong> The Data Privacy Act of 2012 (RA 10173) protects personal information.
                Do not collect, share, or post another person's private details without a lawful reason and consent.
            </div>
        </section>

        <section class="panel policy-section" id="cybercrime">
            <p class="policy-kicker">02 &middot; Cybercrime</p>
            <h2>Cybercrime</h2>
            <p>
                Republic Act No. 10175, the Cybercrime Prevention Act of 2012, covers crimes committed using
                computers, phones, and networks. These include illegal access, illegal interception, data
                interference, identity theft, computer-related fraud, and content-related offenses such as cyber
                libel and child pornography.
            </p>
            <p>Do not use Porchlight to:</p>
            <ul>
                <li>Access another person's account, device, or data without permission.</li>
                <li>Send phishing links, scams, malware, or fraudulent payment requests.</li>
                <li>Steal identities, impersonate public figures, or open accounts for someone else.</li>
                <li>Publish private information to intimidate or expose a person (doxxing).</li>
                <li>Share child sexual abuse material or use the platform to plan violence or other crimes.</li>
            </ul>
            <p>
                We act on credible reports, may preserve relevant account records, and cooperate with lawful
                requests from authorities. Suspected cybercrime can be reported to the PNP Anti-Cybercrime Group
                or the NBI Cybercrime Division.
            </p>
        </section>

        <section class="panel policy-section" id="harassment">
            <p class="policy-kicker">03 &middot; Harassment</p>
            <h2>Harassment of users</h2>
            <p>
                Harassment is not allowed on Porchlight. This includes threats, stalking, bullying, repeated
                unwanted messages, sexual harassment, humiliating posts, and sharing intimate images without
                consent.
            </p>
            <p>
                Philippine protections include RA 11313 (Safe Spaces Act), which covers online gender-based sexual
                harassment, RA 9995 (Anti-Photo and Video Voyeurism Act), and RA 10627 (Anti-Bullying Act) in
                school settings.
            </p>
            <p>
                If you experience harassment: do not engage further, keep screenshots, links, and timestamps,
                report the account and content, and ask someone you trust for support. If there is an immediate
                threat to safety, call 911 or contact your local police.
            </p>
        </section>

        <section class="panel policy-section" id="cyber-libel">
            <p class="policy-kicker">04 &middot; Online defamation</p>
            <h2>Cyber libel</h2>
            <p>
                Cyber libel is the online publication of a false statement that maliciously damages a person's
                reputation, also called online defamation. In the Philippines, it is prosecuted under RA 10175 in
                relation to Article 355 of the Revised Penal Code.
            </p>
            <p>
                Fair criticism, honest opinion, and accurate reporting are not the same as libel. Before
                publishing an accusation, verify the facts and avoid spreading screenshots or claims you cannot
                support.
            </p>
            <ul>
                <li>Do not post knowingly false accusations about a person.</li>
                <li>Do not share private conversations or images to humiliate someone.</li>
                <li>If you believe you are a target, save the post, URL, date, and account details, and seek legal advice before confronting the other person.</li>
            </ul>
        </section>

        <section class="panel policy-section" id="enforcement">
            <p class="policy-kicker">05 &middot; Enforcement</p>
            <h2>How we respond</h2>
            <p>
                We may hide or remove content, restrict features, suspend or close accounts, and preserve relevant
                records when we believe this policy or the law has been violated. Decisions consider context,
                severity, and repeated behavior.
            </p>
            <p>
                To report a problem, contact the Porchlight administrators through the site. For urgent safety
                concerns, contact local authorities first.
            </p>
            <div class="policy-note">
                This page is a plain-language summary, not legal advice. Laws and rules can change; check official
                Philippine government sources for the latest text.
            </div>
        </section>

        <p class="policy-foot">
            <span>Questions about this policy? Talk to the Porchlight team.</span>
            <a href="index.php">Back to feed</a>
        </p>
    </main>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
