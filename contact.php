<?php
// contact.php — JerseyFlow Contact Page
$page_title = "Contact Us — JerseyFlow";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="style/contact.css">
    <link rel="stylesheet" href="style/navbar.css">
    <link rel="stylesheet" href="style/footer.css">
    <link rel="icon" href="images/logo_icon.ico" type="image/x-icon">
</head>
<body>

<?php include("homepage/navbar.php"); ?>

<!-- ===================== CONTACT PAGE ===================== -->
<main class="contact-page">

    <!-- Page Header -->
    <div class="contact-header">
        <span class="section-label">Support &amp; Inquiries</span>
        <h1>Get In <span>Touch</span></h1>
        <p>Have a question about an order, sizing, or a custom kit? We're here to help — reach out and we'll get back to you fast.</p>
    </div>

    <!-- Main Grid -->
    <div class="contact-grid">

        <!-- ---- LEFT: Info Cards ---- -->
        <aside class="contact-info">

            <!-- Email -->
            <div class="info-card">
                <div class="icon-wrap">
                    <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
                </div>
                <div class="info-text">
                    <h3>Email Us</h3>
                    <a href="mailto:support@jerseyflow.com">support@jerseyflow.com</a><br>
                    <a href="mailto:orders@jerseyflow.com">orders@jerseyflow.com</a>
                </div>
            </div>

            <!-- Phone -->
            <div class="info-card">
                <div class="icon-wrap">
                    <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 11.5 19.79 19.79 0 01.02 2.82 2 2 0 012 .67h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 8.47a16 16 0 006.44 6.44l1.18-1.18a2 2 0 012.11-.45c.9.362 1.85.59 2.81.7A2 2 0 0122 16.92z"/></svg>
                </div>
                <div class="info-text">
                    <h3>Call Us</h3>
                    <a href="tel:+9779800000000">+977 980-000-0000</a>
                    <p style="margin-top:3px; font-size:12.5px; color:var(--muted);">Mon–Sat, 10 AM – 6 PM NPT</p>
                </div>
            </div>

            <!-- Location -->
            <div class="info-card">
                <div class="icon-wrap">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                </div>
                <div class="info-text">
                    <h3>Visit Us</h3>
                    <p>Kathmandu, Bagmati Province<br>Nepal</p>
                </div>
            </div>

            <!-- Business Hours -->
            <div class="info-card">
                <div class="icon-wrap">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <div class="info-text">
                    <h3>Business Hours</h3>
                    <ul class="hours-list">
                        <li><span>Mon – Fri</span><span>10:00 AM – 6:00 PM</span></li>
                        <li><span>Saturday</span><span>11:00 AM – 4:00 PM</span></li>
                        <li><span>Sunday</span><span>Closed</span></li>
                    </ul>
                </div>
            </div>

            <!-- Social -->
            <div class="info-card">
                <div class="icon-wrap">
                    <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>
                </div>
                <div class="info-text">
                    <h3>Follow Us</h3>
                    <div class="social-row">
                        <!-- Instagram -->
                        <a href="#" class="social-btn" title="Instagram" aria-label="Instagram">
                            <svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" fill="none" style="fill:var(--muted);stroke:none"/><circle cx="12" cy="12" r="3.2"/><circle cx="17.5" cy="6.5" r="1.5"/></svg>
                        </a>
                        <!-- Facebook -->
                        <a href="#" class="social-btn" title="Facebook" aria-label="Facebook">
                            <svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                        </a>
                        <!-- Twitter / X -->
                        <a href="#" class="social-btn" title="Twitter / X" aria-label="Twitter">
                            <svg viewBox="0 0 24 24"><path d="M4 4l16 16M20 4L4 20"/></svg>
                        </a>
                        <!-- WhatsApp -->
                        <a href="https://wa.me/9779800000000" class="social-btn" title="WhatsApp" aria-label="WhatsApp">
                            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                        </a>
                    </div>
                </div>
            </div>

        </aside>

        <!-- ---- RIGHT: Contact Form ---- -->
        <section class="contact-form-wrap">
            <h2>Send a Message</h2>

            <form action="process_contact.php" method="POST" novalidate>

                <!-- Name row -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" placeholder="Aarav" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" placeholder="Shrestha" required>
                    </div>
                </div>

                <!-- Email & Phone -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone (Optional)</label>
                        <input type="tel" id="phone" name="phone" placeholder="+977 98X-XXX-XXXX">
                    </div>
                </div>

                <!-- Order number -->
                <div class="form-group">
                    <label for="order_id">Order Number (if applicable)</label>
                    <input type="text" id="order_id" name="order_id" placeholder="JF-00000">
                </div>

                <!-- Topic -->
                <div class="form-group">
                    <label for="topic">Topic</label>
                    <select id="topic" name="topic" required>
                        <option value="" disabled selected>Select a topic…</option>
                        <option value="order_status">Order Status / Tracking</option>
                        <option value="return_exchange">Return &amp; Exchange</option>
                        <option value="custom_kit">Custom Kit Inquiry</option>
                        <option value="sizing">Sizing Help</option>
                        <option value="payment">Payment Issue</option>
                        <option value="wholesale">Wholesale / Bulk Order</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" placeholder="Describe your query in detail…" required></textarea>
                </div>

                <hr class="form-divider">

                <!-- Consent -->
                <div class="form-check">
                    <input type="checkbox" id="consent" name="consent" required>
                    <label for="consent">
                        I agree to the <a href="privacy.php">Privacy Policy</a> and consent to JerseyFlow contacting me regarding my inquiry.
                    </label>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-submit">
                    <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send Message
                </button>

            </form>
        </section>

    </div><!-- /.contact-grid -->

    <!-- ---- FAQ Strip ---- -->
    <div class="faq-strip">
        <h2>Common Questions</h2>
        <div class="faq-list">

            <div class="faq-item">
                <h4>How long does shipping take?</h4>
                <p>Standard delivery within Nepal takes 2–5 business days. Kathmandu Valley orders typically arrive within 1–2 days.</p>
            </div>

            <div class="faq-item">
                <h4>Can I return or exchange a jersey?</h4>
                <p>Yes — unused items in original condition can be returned within 7 days of delivery. Custom kits are non-refundable.</p>
            </div>

            <div class="faq-item">
                <h4>Do you do custom / bulk orders?</h4>
                <p>Absolutely. Select "Custom Kit Inquiry" in the form above and share your team details. We'll respond within 24 hours.</p>
            </div>

            <div class="faq-item">
                <h4>Which payment methods do you accept?</h4>
                <p>We accept eSewa, Khalti, bank transfer, and cash on delivery for orders within Kathmandu Valley.</p>
            </div>

        </div>
    </div>

</main>
<!-- ==================== END CONTACT PAGE ==================== -->

<?php include("footer.php"); ?>

</body>
</html>