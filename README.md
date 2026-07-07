# 🌐 W3A — News Aggregator in the Style of Hacker News and Lobsters

🇬🇧 English | 🇷🇺 [Русский](README.ru.md)

A modern, lightweight news aggregator built with **PHP 8.1+** and **MySQL**, inspired by [Hacker News](https://news.ycombinator.com) and [Lobste.rs](https://lobste.rs). Features modular HMVC architecture, custom DI container, event-driven system, RSS feeds, bookmarks, user muting, and comprehensive moderation tools.

![W3A home](https://raw.githubusercontent.com/evgip/soc/main/public/github-home.png)

![W3A admin](https://raw.githubusercontent.com/evgip/soc/main/public/github-admin.png)

![W3A setting](https://raw.githubusercontent.com/evgip/soc/main/public/github-setting.png)

---

## 🚀 Features

### Core Functionality
- **Stories** — Submit news with URL or text content
- **Threaded Comments** — Nested comments with unlimited depth
- **Global Comments Feed** — `/comments` page showing all recent comments across the site (Lobsters-style)
- **User Comments** — `/user/{username}/comments` page showing all comments by a user
- **User Stories** — `/user/{username}/stories` page showing user's publication history
- **Voting System** — Upvote/downvote; downvoting requires minimum karma
- **Authentication** — Registration, login, "Remember me", email activation, password recovery
- **User Profiles** — Bio, avatar, karma, activity feed
- **Private Messages** — Direct messaging between users
- **Notifications** — Internal notifications for replies, story comments, mentions, moderator actions
- **Tag Wiki** — Wiki pages linked to tags with edit history and editing permissions
- **Edit Suggestions** — Users can suggest changes to stories (tags, title) that go through moderation

### Personalization & Tracking
- **Read Ribbons** — Track read comments in each story; "+N new" badges in the feed
- **New Comment Highlighting** — Visual highlighting of unread comments in stories
- **Global Comment Tracking** — "↑ New comments ↓" divider on `/comments` page
- **Saved Stories (Bookmarks)** — Save stories to a personal "read later" list at `/saved`
- **User Muting** — Hide stories, comments, and notifications from muted users
- **Tag Filters** — Users can hide stories by uninteresting tags
- **Story Following** — Get notifications for new comments in followed stories
- **Notification Settings** — Granular control: email, internal notifications, by event type

### RSS Feeds
- `/rss` — All new stories
- `/t/{tag}/rss` — Stories by specific tag
- `/u/{username}/rss` — Stories by user
- `/comments/rss` — All new comments
- Browser auto-discovery via `<link rel="alternate">`

### Ranking & Search
- **Hotness Algorithm** — Custom formula considering score, age, and tag weight modifier
- **Wilson Score Interval** — Statistically sound comment ranking by confidence
- **Tag System** — Stories are tagged; tags are organized into categories
- **Full-Text Search** — Search across stories and comments

### Moderation & Trust
- **Invitation System** — Can disable open registration; only users with sufficient karma can invite
- **Flag System** — Users can flag content (spam, abuse, duplicates, etc.); content auto-hidden at threshold
- **Domain Management** — Ban entire domains; stories from banned domains auto-rejected
- **User Bans** — Temporary or permanent bans with reason
- **IP Bans** — Block abusive IP addresses
- **Moderator Notes** — Private notes on user accounts, visible only to staff
- **Moderation Activity Log** — Daily statistics of moderator actions
- **Audit Log** — Complete trail of every administrative action
- **Public Moderation Log** — Transparency of moderator actions for all users

### Architecture & Developer Experience
- **DI Container** — Lightweight dependency injection container
- **Event Dispatcher** — Observer pattern: `StoryCreated`, `CommentCreated`, `CommentUpdated`, `UserBanned`, `FlagResolved`, etc.
- **Middleware Pipeline** — HTTP middleware for auth, CSRF, rate limiting, security headers
- **Modular HMVC** — Each feature is a self-contained module (Controllers / Models / Views / Services / routes)
- **Service Providers** — Per-module service registration
- **Separation of Concerns** — Comments, bookmarks, muting, RSS, wiki — in separate modules

### Security
- **Password Hashing** — bcrypt with automatic salting
- **CSRF Protection** — Token verification on every state-changing form
- **SQL Injection Protection** — Prepared PDO statements everywhere
- **XSS Protection** — Output escaping via `htmlspecialchars()`
- **Rate Limiting** — Configurable limits per action and IP
- **Session Security** — HTTP-only cookies, session regeneration
- **Input Validation** — Server-side validation on every endpoint
- **Security Headers** — Strict-Transport-Security, X-Frame-Options, etc.
- **CAPTCHA** — Built-in CAPTCHA for sensitive actions
- **Firewall** — Request filtering and IP reputation checking

### User Experience
- **Responsive Design** — Mobile-friendly interface
- **Light & Dark Themes** — Auto-detection from system settings + manual toggle via `localStorage`
- **CSS Variables** — Unified theming system via `:root` and `[data-theme="dark"]`
- **Keyboard Shortcuts** — `C` to collapse comments and others
- **Markdown Support** — Text formatting in comments and stories (via Parsedown)
- **Comment Thread Collapsing** — With state persistence in `localStorage`
- **Markdown Toolbar** — Formatting buttons with live preview

---

## 🛠️ Tech Stack

| Component       | Technology                                       |
|-----------------|--------------------------------------------------|
| **Backend**     | PHP 8.1+ (no framework, custom HMVC + DI)        |
| **Database**    | MySQL 8.0+ with PDO                              |
| **Frontend**    | Vanilla JavaScript, CSS3 with CSS Variables      |
| **Architecture**| Modular HMVC, Event Dispatcher, Service Provider |
| **Email**       | PHPMailer 7.x                                    |
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

### Step 2 — Create Database

1. Create a MySQL database:

```sql
CREATE DATABASE w3a CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the schema:

```bash
mysql -u your_username -p w3a < db/schema.sql
```

### Step 3 — Configure Application

Rename `.env.example` to `.env` and fill in your settings.

### Step 4 — Set Permissions

```bash
chmod -R 755 storage/
chmod -R 755 public/
```

### Step 5 — Configure Web Server

**Apache** (`.htaccess` included):

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

| Role           | Email              | Password  |
|----------------|--------------------|-----------| 
| Administrator  | admin@example.com  | password  |
| User           | test@test.ru       | password  |

> ⚠️ Change these passwords immediately after first login.

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
│   │   ├── Audit.php               # Audit log
│   │   ├── RateLimiter.php         # Rate limiting
│   │   ├── Captcha.php             # CAPTCHA generator
│   │   ├── Firewall.php            # Request firewall
│   │   ├── Logger.php              # Application logger
│   │   ├── Benchmark.php           # Performance measurement
│   │   ├── SvgChart.php            # SVG chart generator
│   │   ├── ModuleServiceProvider.php
│   │   ├── Events/                 # Event system
│   │   │   ├── Event.php
│   │   │   ├── EventDispatcher.php
│   │   │   ├── StoryCreated.php
│   │   │   ├── StoryDeleted.php
│   │   │   ├── CommentCreated.php
│   │   │   ├── CommentUpdated.php
│   │   │   ├── CommentDeleted.php
│   │   │   ├── CommentRestored.php
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
│   │   ├── Admin/                  # Admin panel
│   │   ├── Auth/                   # Authentication
│   │   ├── Captcha/                # CAPTCHA generation
│   │   ├── Comments/               # Comments (global feed, CRUD)
│   │   ├── Common/                 # Shared views, base CSS
│   │   ├── Content/                # Markdown processor
│   │   ├── Errors/                 # Error handling
│   │   ├── Flags/                  # Content flags
│   │   ├── Invitations/            # Invitation system
│   │   ├── Mail/                   # Email sending
│   │   ├── Messages/               # Private messages
│   │   ├── Moderations/            # Moderation tools
│   │   ├── Muted/                  # User muting
│   │   ├── Notifications/          # User notifications
│   │   ├── Origins/                # Domain management
│   │   ├── Pages/                  # Static pages
│   │   ├── Rss/                    # RSS feeds
│   │   ├── Saved/                  # Bookmarks (saved stories)
│   │   ├── Search/                 # Full-text search
│   │   ├── Stories/                # Stories and feed
│   │   ├── Suggestions/            # Edit suggestions
│   │   ├── Tags/                   # Tags and categories
│   │   ├── Users/                  # User profiles and settings
│   │   ├── Votes/                  # Voting system
│   │   └── Wiki/                   # Tag wiki pages
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
| Table           | Description                                        |
|-----------------|----------------------------------------------------|
| `stories`       | Stories (URL or text), with hotness score          |
| `comments`      | Threaded comments with confidence score            |
| `votes`         | Polymorphic up/down votes                          |
| `tags`          | Tag definitions with hotness modifier              |
| `taggings`      | Many-to-many relationship between stories and tags |
| `categories`    | Tag categories                                     |
| `tag_filters`   | User tag exclusions                                |
| `wiki_pages`    | Wiki pages linked to tags                          |
| `wiki_revisions`| Wiki page edit history                             |
| `wiki_permissions`| Wiki editing permissions by tag                  |
| `suggestions`   | Story edit suggestions (tags, titles)              |

### Users & Authentication
| Table                | Description                                    |
|----------------------|------------------------------------------------|
| `users`              | User accounts (role: user / moderator / admin) |
| `user_profiles`      | Bio, avatar                                    |
| `user_settings`      | Notification settings                          |
| `user_bans`          | Temporary / permanent bans                     |
| `banned_ips`         | IP blocks                                      |
| `password_resets`    | Password recovery tokens                       |
| `email_activations`  | Email confirmation tokens                      |
| `invitations`        | Invitation codes                               |
| `invitation_requests`| Public invitation requests                     |

### Social & Personalization
| Table                | Description                                    |
|----------------------|------------------------------------------------|
| `conversations`      | Private message conversations                  |
| `messages`           | Individual messages                            |
| `user_notifications` | Internal notifications                         |
| `read_ribbons`       | Last read comment in a story                   |
| `saved_stories`      | User bookmarks (saved stories)                 |
| `muted_users`        | Muted/ignored users                            |

### Moderation
| Table          | Description                                         |
|----------------|-----------------------------------------------------|
| `flags`        | Content flags (spam, abuse, etc.)                   |
| `domains`      | Banned / allowed domains                            |
| `mod_notes`    | Private moderator notes about users                 |
| `mod_activity` | Daily moderator action counts                       |
| `audit_logs`   | Complete administrative action log                  |

### Infrastructure
| Table          | Description                                         |
|----------------|-----------------------------------------------------|
| `sessions`     | Active user sessions                                |
| `rate_limits`  | Rate limit counters                                 |

Full schema with indexes and foreign keys — in `db/schema.sql`.

---

## 🗺️ URL Map

### Public Pages
| URL                            | Description                             |
|--------------------------------|-----------------------------------------|
| `/`                            | Main story feed                         |
| `/story/{id}`                  | View story with comments                |
| `/comments`                    | Global comments feed                    |
| `/comments/rss`                | RSS of all new comments                 |
| `/rss`                         | RSS of all new stories                  |
| `/t/{tag}`                     | Stories with specific tag               |
| `/t/{tag}/rss`                 | RSS of stories by tag                   |
| `/t/{tag}/wiki`                | Wiki pages for tag                      |
| `/t/{tag}/wiki/{slug}`         | Specific wiki page                      |
| `/u/{username}`                | User profile                            |
| `/u/{username}/stories`        | User's publications                     |
| `/u/{username}/comments`       | User's comments                         |
| `/u/{username}/rss`            | RSS of user's publications              |
| `/search`                      | Full-text search                        |
| `/tags`                        | List of all tags                        |
| `/stats`                       | Site statistics                         |

### Authenticated Pages
| URL                            | Description                             |
|--------------------------------|-----------------------------------------|
| `/saved`                       | User bookmarks                          |
| `/muted`                       | List of muted users                     |
| `/notifications`               | Internal notifications                  |
| `/messages`                    | Private messages                        |
| `/settings`                    | Account settings                        |
| `/tags/filters`                | User tag filters                        |
| `/story/create`                | Create new story                        |

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
- Speed and simplicity philosophy from [**HLEB**](https://github.com/phphleb/hleb) framework
- Built with pure PHP — no heavy frameworks
- Modular HMVC architecture with DI and events

## 📧 Contact

- **Author**: Evgeny Konchik (Evg)
- **Repository**: https://github.com/evgip/w3a
- **Bugs & Suggestions**: https://github.com/evgip/w3a/issues

---

**⭐ If you find this project useful, please star it on GitHub!**