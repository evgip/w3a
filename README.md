# 🌐 W3A - Hacker News and Lobster-style news aggregator

[🇷🇺 Русский](README.ru.md) | [🇬🇧 English](README.md)

A modern, lightweight news aggregator built with PHP 8.1+ and MySQL, inspired by Hacker News. Features a modular HMVC architecture, real-time updates, and comprehensive moderation tools.

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange)
![License](https://img.shields.io/badge/License-MIT-green)

 
![W3A home](https://raw.githubusercontent.com/evgip/soc/main/public/github-home.png)


![W3A admin](https://raw.githubusercontent.com/evgip/soc/main/public/github-admin.png)


![W3A setting](https://raw.githubusercontent.com/evgip/soc/main/public/github-setting.png)

## 🚀 Features

### Core Functionality
- **News Stories** - Submit and browse news stories with URL or text content
- **Comments** - Threaded comment system with nested replies
- **Voting System** - Upvote/downvote stories and comments with real-time updates
- **User Authentication** - Secure registration, login, and session management
- **User Profiles** - Activity tracking, submitted stories, and comment history

### Advanced Features
- **Real-time Updates** - AJAX-powered live updates for votes and comments
- **Moderation Tools** - Ban users, domains, and manage content
- **Audit Logging** - Comprehensive activity tracking for all administrative actions
- **Rate Limiting** - Protection against abuse with configurable limits
- **CSRF Protection** - Security tokens for all form submissions
- **Soft Deletes** - Recoverable content deletion
- **Search** - Full-text search across stories and comments

### User Experience
- **Responsive Design** - Mobile-friendly interface
- **Dark Mode** - System-aware theme switching
- **Keyboard Shortcuts** - Power-user navigation
- **Markdown Support** - Rich text formatting in comments

## 🛠️ Technology Stack

| Component | Technology |
|-----------|-----------|
| **Backend** | PHP 8.1+ (no framework, custom HMVC) |
| **Database** | MySQL 8.0+ with PDO |
| **Frontend** | Vanilla JavaScript, CSS3 |
| **Architecture** | Modular HMVC pattern |
| **Security** | CSRF tokens, rate limiting, password hashing (bcrypt) |
| **Server** | Apache/Nginx with mod_rewrite |

## 📦 Installation

### Requirements
- PHP 8.1 or higher
- MySQL 8.0 or higher
- Apache/Nginx with URL rewriting enabled
- Composer (optional, for development)

### Step 1: Clone Repository

```bash
git clone https://github.com/evgip/w3a.git
cd w3a
```

### Step 2: Configure Database

1. Create a MySQL database:
```sql
CREATE DATABASE w3a CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the schema:
```bash
mysql -u your_username -p w3a < db/schema.sql
```

3. Update database credentials in `app/Config/config.phpp`:
```php
'database' => [
	'host' => 'MySQL-8.2',
	'port' => '3306',
	'dbname' => 'soc',
	'username' => 'root',
	'password' => '',
	'charset' => 'utf8mb4',
]
```

### Step 3: Configure Application

Edit `app/Config/config.php`:
```php
'app' => [
	'name' => 'w3a',
	'url' => 'http://soc.local',
	'lang' => en', 
```

### Step 4: Set Permissions

```bash
chmod -R 755 storage/
chmod -R 755 public/
```

### Step 5: Configure Web Server

**Apache** (`.htaccess` already included):
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

### Step 6: Login information

- Admin: admin@example.com / password
- User: test@test.ru / password

## 📁 Project Structure

```
w3a/
├── app/
│	├── Config/                 # Configuration
│   │	└── config.php          # App settings
│   ├── Core/                   # Core framework classes
│   │   ├── Router.php          # URL routing
│   │   ├── Controller.php      # Base controller
│   │   ├── Model.php           # Base model
│   │   ├── Database.php        # PDO wrapper
│   │   ├── Auth.php            # Authentication
│   │   ├── Session.php         # Session management
│   │   ├── Request.php         # HTTP request handling
│   │   ├── Response.php        # HTTP responses
│   │   ├── View.php            # Template rendering
│   │   ├── Audit.php           # Audit logging
│   │   └── Validator.php       # Input validation
│   │
│   └── Modules/                 # Feature modules (HMVC)
│       ├── Auth/               # Authentication module
│       │   ├── Controllers/
│       │   ├── Models/
│       │   ├── Views/
│       │   └── routes.php
│       ├── Stories/            # News stories module
│       ├── Comments/           # Comments module
│       ├── Votes/              # Voting system
│       ├── Users/              # User profiles
│       ├── Moderation/         # Moderation tools
│       ├── Origins/            # Domain management
│       └── Api/                # REST API
├── public/                      # Public assets
│   ├── index.php              # Entry point
│   ├── css/                   # Stylesheets
│   ├── js/                    # JavaScript files
│   └── images/                # Images
│
├── storage/                     # Writable storage
│   ├── logs/                  # Application logs
│   └── cache/                 # Cache files
│
├── db/                          # Database
│   └── schema.sql             # Database schema
│
└── README.md                    # This file
```

## 🔒 Security Features

- **Password Hashing** - bcrypt with automatic salt
- **CSRF Protection** - Token validation on all forms
- **SQL Injection Prevention** - Prepared statements (PDO)
- **XSS Protection** - Output escaping with `htmlspecialchars()`
- **Rate Limiting** - Configurable per-action limits
- **Session Security** - HTTP-only cookies, regeneration
- **Input Validation** - Server-side validation for all inputs

## 🧪 Development

### Debug Mode
Enable in `app/Config/config.php`:
```php
'env' => 'development', // development или production
```

## 📊 Database Schema

### Core Tables
- `users` - User accounts and profiles
- `stories` - News stories (URL or text)
- `comments` - Threaded comments
- `votes` - Upvotes/downvotes
- `sessions` - Active user sessions
- `audit_logs` - Administrative activity log
- `domains` - Banned domains list
- `flags` - Complaint log

See `db/schema.sql` for complete schema.

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by the functionality of [Hacker News](https://news.ycombinator.com/) and [Lobster](https://lobste.rs/)
- Speed and simplicity of the [HLEB](https://hleb2framework.ru/) framework
- Built with vanilla PHP (no frameworks)
- Modular HMVC architecture

## 📧 Contact

- **Author**: Evg
- **Repository**: https://github.com/evgip/w3a
- **Issues**: https://github.com/evgip/w3a/issues

---

**⭐ If you find this project useful, please consider giving it a star on GitHub!**