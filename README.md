# 🎓 University Marketplace System (UMS)

A PHP-based peer-to-peer marketplace for University of Nairobi students to buy and sell items safely within the campus community.

## Features

- **Authentication** — UoN student email validation, email verification, forgot password
- **Marketplace** — Browse, search, filter, post and edit listings
- **AI Verification** — Imagga API image verification with auto cleanup
- **M-Pesa Payments** — Daraja STK Push integration
- **Enquiry System** — Ask questions and get replies on listings
- **Community Help** — Real-time AJAX community sidebar
- **Report System** — Report inappropriate listings
- **Admin Panel** — Manage users, listings and reports
- **Security** — CSP headers, HttpOnly cookies, XSS protection, PDO prepared statements

## 💰 Platform Fee Policy

UMS charges a **5% platform fee** on all completed transactions.

| Item Price | Platform Fee (5%) | Seller Receives |
|---|---|---|
| KSh 100 | KSh 10 (min) | KSh 90 |
| KSh 500 | KSh 25 | KSh 475 |
| KSh 1,000 | KSh 50 | KSh 950 |
| KSh 5,000 | KSh 250 | KSh 4,750 |

**Fee Purpose:**
- Platform server maintenance
- Content moderation
- Student welfare programs
- System improvements

> The buyer always pays the listed price. The fee is deducted from the seller's payout.

## Tech Stack

- **Backend:** PHP 8.x
- **Database:** MySQL (via phpMyAdmin)
- **Frontend:** HTML5, CSS3, JavaScript (AJAX)
- **Email:** PHPMailer + Gmail SMTP
- **Payments:** Safaricom M-Pesa Daraja API
- **AI:** Imagga Computer Vision API
- **Server:** Apache (XAMPP)

## Setup Instructions

### Requirements
- XAMPP (PHP 8.x + MySQL + Apache)
- Composer
- Gmail account with App Password
- Safaricom Daraja sandbox account
- Imagga API account

### Installation

1. Clone the repository:
\```
git clone https://github.com/YOUR_USERNAME/university-marketplace-system.git
\```

2. Move to XAMPP htdocs:
\```
mv university-marketplace-system C:/xampp/htdocs/ums
\```

3. Import the database:
- Open phpMyAdmin
- Create database named `ums`
- Import `database/ums.sql`

4. Configure credentials:
\```
cp includes/mailer.example.php includes/mailer.php
cp mpesa/config.example.php mpesa/config.php
cp includes/vision.example.php includes/vision.php
\```
Then fill in your credentials in each file.

5. Install PHPMailer:
\```
composer require phpmailer/phpmailer
\```

6. Visit:
\```
http://localhost/ums
\```

## Default Admin Account
- **Email:** admin@students.uonbi.ac.ke
- **Password:** admin123

> ⚠️ Change the admin password immediately after first login!

## Project Structure

\```
ums/
├── auth/           ← Authentication pages
├── admin/          ← Admin panel
├── community/      ← Community help AJAX endpoints
├── mpesa/          ← M-Pesa Daraja integration
├── includes/       ← Shared PHP files
├── css/            ← Stylesheets
├── js/             ← JavaScript files
├── uploads/        ← User uploaded files
└── database/       ← SQL schema
\```

## Developer

**Joel Nguru Kamau**
University of Nairobi — 2nd Year Project