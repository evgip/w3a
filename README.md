# 🌐 W3A — Hacker News & Lobster-style News Aggregator

🇷🇺 [Русский](README.ru.md) | 🇬🇧 English

A modern, lightweight news aggregator built with **PHP 8.1+** and **MySQL**, inspired by [Hacker News](https://news.ycombinator.com) and [Lobste.rs](https://lobste.rs). Features a modular HMVC architecture, a custom DI container, an event-driven system, real-time updates, and comprehensive moderation tools.

![W3A home](https://raw.githubusercontent.com/evgip/soc/main/public/github-home.png)


![W3A admin](https://raw.githubusercontent.com/evgip/soc/main/public/github-admin.png)


![W3A setting](https://raw.githubusercontent.com/evgip/soc/main/public/github-setting.png)


---

## 🚀 Features

### Core Functionality
- **News Stories** — Submit stories with a URL or plain text
- **Threaded Comments** — Nested, infinitely replyable comment trees
- **Voting System** — Upvote / downvote stories and comments; downvotes require minimum karma
- **User Authentication** — Registration, login, "Remember me", email activation, password recovery
- **User Profiles** — Bio, avatar, activity feed, submitted stories, comment history
- **Private Messages** — One-to-one conversations between users
- **Notifications** — In-app notifications for replies, comments on followed stories, moderator actions

### Ranking & Discovery
- **Hotness Algorithm** — Custom ranking formula combining vote score, age, and per-tag weight modifiers
- **Wilson Score Interval** — Statistically sound confidence ranking for comments
- **Tag System** — Stories can be tagged; tags belong to categories
- **Tag Filters** — Users can hide stories by tags they don't want to see
- **Full-text Search** — Search across stories and comments
- **Read Ribbons** — Track which comments you've already read; highlights new ones

### Moderation & Trust
- **Invitation System** — Toggle open registration on/off; only users with enough karma can send invites
- **Flag / Report System** — Users can flag content (spam, offensive, duplicate, etc.); auto-hides at threshold
- **Domain Management** — Ban entire domains; stories from banned domains are auto-rejected
- **User Bans** — Temporary or permanent bans with reason, issued by moderators
- **IP Bans** — Block abusive IP addresses
- **Moderator Notes** — Private notes attached to user accounts, visible only to staff
- **Moderator Activity Log** — Daily statistics of moderation actions
- **Audit Log** — Full trail of every administrative action

### Architecture & DX
- **DI Container** — Lightweight service container for dependency injection
- **Event Dispatcher** — Observer pattern: `StoryCreated`, `CommentCreated`, `UserBanned`, `FlagResolved`, etc.
- **Middleware Pipeline** — HTTP middleware for auth, CSRF, rate-limiting, security headers
- **Modular HMVC** — Each feature is a self-contained module (Controllers / Models / Views / routes)
- **Service Providers** — Per-module service registration

### Security
- **Password Hashing** — bcrypt with automatic salt
- **CSRF Protection** — Token validation on every state-changing form
- **SQL Injection Prevention** — PDO prepared statements everywhere
- **XSS Protection** — Output escaping via `htmlspecialchars()`
- **Rate Limiting** — Configurable per-action, per-IP limits
- **Session Security** — HTTP-only cookies, session regeneration
- **Input Validation** — Server-side validation on every endpoint
- **Security Headers** — Strict-Transport-Security, X-Frame-Options, etc.
- **CAPTCHA** — Built-in CAPTCHA for sensitive actions
- **Firewall** — Request filtering and IP reputation checks

### User Experience
- **Responsive Design** — Mobile-friendly layout
- **Dark Mode** — System-aware theme switching
- **Keyboard Shortcuts** — Power-user navigation
- **Markdown Support** — Rich text formatting in comments and stories (via Parsedown)
- **User Settings** — Granular notification preferences (email, in-app, per-event)

---

## 🛠️ Technology Stack

| Component       | Technology                                       |
|-----------------|--------------------------------------------------|
| **Backend**     | PHP 8.1+ (no framework, custom HMVC + DI)        |
| **Database**    | MySQL 8.0+ with PDO                              |
| **Frontend**    | Vanilla JavaScript, CSS3                         |
| **Architecture**| Modular HMVC, Event Dispatcher, Service Provider |
| **Mail**        | PHPMailer 7.x                                    |
| **Markdown**    | Parsedown 1.8+                                   |
| **Security**    | CSRF tokens, rate limiting, bcrypt, CAPTCHA      |
| **Server**      | Apache / Nginx with URL rewriting                |

---

## 📦 Installation

Install via Composer:

```bash
composer create-project evgip/w3a
```

### Requirements
- PHP 8.1 or higher
- MySQL 8.0 or higher
- Apache / Nginx with URL rewriting enabled
- Composer (optional, for development)

### Step 1 — Clone the Repository

```bash
git clone https://github.com/evgip/w3a.git
cd w3a
```

### Step 2 — Creating a database

1. Create a MySQL database:

```sql
CREATE DATABASE w3a CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the schema:

```bash
mysql -u your_username -p w3a < db/schema.sql
```


### Step 3 — Configure the Application

Rename the `.env.example` file to `.env` and fill it in.

### Step 4 — Set Permissions

```bash
chmod -R 755 storage/
chmod -R 755 public/
```

### Step 5 — Configure the Web Server

**Apache** (`.htaccess` is included):

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Nginx**:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Step 6 — Default Accounts

| Role     | Email                | Password  |
|----------|----------------------|-----------|
| Admin    | admin@example.com    | password  |
| User     | test@test.ru         | password  |

> ⚠️ Change these credentials immediately after the first login.

---

## 📁 Project Structure

```
w3a/
├── app/
│   ├── Config/
│   │   └── config.php              # Application settings
│   ├── Core/
│   │   ├── Container.php           # DI container
│   │   ├── Router.php              # URL routing
│   │   ├── Controller.php          # Base controller
│   │   ├── Model.php               # Base model
│   │   ├── Database.php            # PDO wrapper
│   │   ├── Session.php             # Session management
│   │   ├── Request.php             # HTTP request handling
│   │   ├── Security.php            # Security headers
│   │   ├── View.php                # Template rendering
│   │   ├── Validator.php           # Input validation
│   │   ├── Audit.php               # Audit logging
│   │   ├── RateLimiter.php         # Rate limiting
│   │   ├── Firewall.php            # Request firewall
│   │   ├── Logger.php              # Application logger
│   │   ├── Benchmark.php           # Performance benchmarking
│   │   ├── SvgChart.php            # SVG chart generator
│   │   ├── ModuleServiceProvider.php
│   │   ├── Events/                 # Event system
│   │   │   ├── Event.php
│   │   │   ├── EventDispatcher.php
│   │   │   ├── StoryCreated.php
│   │   │   ├── StoryDeleted.php
│   │   │   ├── CommentCreated.php
│   │   │   ├── CommentDeleted.php
│   │   │   ├── UserBanned.php
│   │   │   ├── FlagResolved.php
│   │   │   └── ...
│   │   └── Middleware/             # HTTP middleware
│   │
│   ├── Providers/
│   │   └── EventServiceProvider.php
│   │
│   ├── Lang/                       # Localization files
│   │
│   ├── Modules/                    # Feature modules (HMVC)
│   │   ├── Admin/                  # Administration panel
│   │   ├── Stories/                # News stories
│   │   ├── Auth/               	# Auth
│   │   ├── Captcha/               	# Captcha/
│   │   ├── Content/               	# Markdown
│   │   ├── Mail/               	# Mail
│   │   ├── Comments/               # Comments
│   │   ├── Votes/                  # Voting system
│   │   ├── Tags/                   # Tags & categories
│   │   ├── Users/                  # User profiles & settings
│   │   ├── Messages/               # Private messages
│   │   ├── Notifications/          # User notifications
│   │   ├── Flags/                  # Content reporting
│   │   ├── Invitations/            # Invitation system
│   │   ├── Origins/                # Domain management
│   │   ├── Moderations/            # Moderation tools
│   │   ├── Search/                 # Full-text search
│   │   ├── Pages/                  # Static pages
│   │   ├── Errors/                 # Error handling
│   │   └── Common/                 # Shared views & components
│   │
│   └── helpers.php                 # Global helper functions
│
├── public/                         # Web root
│   ├── index.php                   # Entry point
│   ├── css/
│   ├── js/
│   └── images/
│
├── storage/                        # Writable storage
│   ├── logs/                       # Application logs
│   └── cache/                      # Cache files
│
├── db/
│   └── schema.sql                  # Database schema
│
├── composer.json
└── README.md
```

---

## 📊 Database Schema

### Content
| Table             | Description                                    |
|-------------------|------------------------------------------------|
| `stories`         | News stories (URL or text), with hotness score |
| `comments`        | Threaded comments with confidence score        |
| `votes`           | Polymorphic upvotes / downvotes                |
| `tags`            | Tag definitions with hotness modifier          |
| `taggings`        | Many-to-many link between stories and tags     |
| `categories`      | Tag categories                                 |
| `tag_filters`     | Per-user tag exclusions                        |

### Users & Auth
| Table                | Description                                      |
|----------------------|--------------------------------------------------|
| `users`              | User accounts (role: user / moderator / admin)   |
| `user_profiles`      | Bio, avatar                                      |
| `user_settings`      | Notification preferences                         |
| `user_bans`          | Temporary / permanent bans                       |
| `banned_ips`         | IP-based blocks                                  |
| `password_resets`    | Password recovery tokens                         |
| `email_activations`  | Email verification tokens                        |
| `invitations`        | Invitation codes                                 |
| `invitation_requests`| Public invite requests                           |

### Social
| Table                | Description                                      |
|----------------------|--------------------------------------------------|
| `conversations`      | Private message threads                          |
| `messages`           | Individual messages                              |
| `user_notifications` | In-app notifications                             |
| `read_ribbons`       | Tracks last-read comment per story               |

### Moderation
| Table           | Description                                       |
|-----------------|---------------------------------------------------|
| `flags`         | Content reports (spam, offensive, etc.)           |
| `domains`       | Banned / allowed domains                          |
| `mod_notes`     | Private moderator notes on users                  |
| `mod_activity`  | Daily moderation action counts                    |
| `audit_logs`    | Full administrative audit trail                   |

### Infrastructure
| Table          | Description                                     |
|----------------|-------------------------------------------------|
| `sessions`     | Active user sessions                            |
| `rate_limits`  | Rate-limiting counters                          |

See `db/schema.sql` for the complete schema with indexes and foreign keys.

---

## 🤝 Contributing

Contributions are welcome!

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📝 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by the functionality and design of **Hacker News** and **Lobste.rs**
- Speed and simplicity of the [**HLEB**](https://github.com/phphleb/hleb) framework philosophy
- Built with vanilla PHP — no heavy frameworks
- Modular HMVC architecture with DI and events

## 📧 Contact

- **Author**: Evgeny Konchik (Evg)
- **Repository**: https://github.com/evgip/w3a
- **Issues**: https://github.com/evgip/w3a/issues

---

**⭐ If you find this project useful, please consider giving it a star on GitHub!**