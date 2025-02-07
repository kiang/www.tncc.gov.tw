# Tainan City Council News Backup

This project automatically fetches news from the [Tainan City Council website](https://www.tncc.gov.tw/) and posts them to the [臺南市議會焦點新聞](https://www.facebook.com/tainan.focus) Facebook page.

## Requirements

- PHP 7.0 or higher
- Composer
- Git

## Features

- Fetches news from multiple sections:
  - 議會快訊 (Council News)
  - 議員園地 (Councilor Zone)
  - 議政資訊公告 (Council Information Announcements)
- Saves content locally with JSON metadata
- Automatically posts news with images to Facebook

## Setup

1. Clone the repository and install dependencies:
```bash
git clone [repository-url]
cd www.tncc.gov.tw
composer install
```

2. Copy the configuration file:
```bash
cp scripts/config_ex.php scripts/config.php
```

3. Set up Facebook credentials in `config.php`:
- Get token from https://developers.facebook.com/tools/explorer/
- Required permissions: pages_show_list, pages_read_engagement, pages_manage_posts
- Extend token expiration at https://developers.facebook.com/tools/debug/accesstoken/

## Dependencies

The project uses the following main dependencies:
- facebook/graph-sdk (^5.7) - Official Facebook SDK for PHP

## Usage

### Manual Fetch
```bash
php scripts/01_fetch_all.php   # Fetch all historical news
php scripts/03_fetch_update.php # Fetch recent updates and post to Facebook
```

### Automated Updates
Set up a cron job to run `scripts/cron.php` periodically. This will:
- Pull latest code changes
- Fetch new updates
- Commit changes to git
- Push to remote repository

## Data Structure

- `/raw/` - Raw HTML files from the council website
- `/data/YYYY/MM/` - Processed JSON files organized by date
