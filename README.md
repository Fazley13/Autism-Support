# ParentConnect – Parent Supporting System for an Autistic Child

A web app (PHP 8 + MySQL) that gives parents, caregivers and educators one calm place for
**community, behaviour tracking, daily planning, events, resources, expert Q&A, private messaging and reports**.

## Features
| Area | What you get |
|---|---|
| Newsfeed | Posts with photos, privacy (public / followers / only me), topics, likes, comments, follow, suggested parents, daily encouragement |
| Profile | Cover + avatar upload, bio, child details, followers/following, change password |
| Search | Global search across people, posts, resources, events, questions |
| Behaviour tracking | Log with 0–5 intensity, edit/delete, filters, **analysis charts, patterns/heat-map, printable report + CSV export** |
| Daily planner | Tasks, priorities, filters, progress bar, overdue tracking |
| Events | Create/edit/delete, RSVP (Going / Interested), attendee list, filters |
| Resources | Links & YouTube videos with in-page player, add/edit/delete |
| Q&A | Ask, answer, edit/delete, search, notifications |
| Messaging | Private 1-to-1 chat with live polling and unread badges |
| Notifications | Bell with unread count; likes, comments, follows, RSVPs, answers |
| Therapy log | Sessions, therapist, feedback rating, charts |

## Tech stack
HTML5 · CSS3 (design system in `assets/css/style.css`, dark-mode aware) · vanilla JS · PHP 8.1+ (mysqli, prepared statements) · MySQL 5.7+/8 or MariaDB 10.4+.

## Project structure
```
api/index.php        Vercel entry point (routes every request to the right PHP file)
assets/              css, js, images (logo, favicons, default avatars)
auth/                login, signup, logout
config/              config.php (env-driven DB + settings), functions.php (helpers)
database/            autism_support_system.sql (full schema + data + v2 additions), upgrade_v2.sql
includes/            bootstrap, session (DB-backed), header, footer, behaviour sub-nav
modules/social/      newsfeed, profile, search, therapy log, messages, APIs
modules/planner/     daily planner, events, attendees
modules/behavior/    analysis, patterns, reports, behaviour types
modules/reports/     behaviour log, resources, Q&A, notifications
uploads/             legacy sample files (new uploads are stored in the database)
media.php            serves uploaded images from the `media` table
vercel.json          Vercel configuration
```

## Run locally with XAMPP
1. Copy the folder to `htdocs/Autism-Support`.
2. phpMyAdmin → create database `autism_support_system` (collation `utf8mb4_general_ci`) → **Import** `database/autism_support_system.sql`.
3. Start Apache + MySQL and open `http://localhost/Autism-Support/`.
4. Default DB settings (root / empty password) work out of the box. To change them, edit the defaults in
   `config/config.php` or create `config/config.local.php`:
   ```php
   <?php return ['DB_HOST'=>'localhost','DB_USER'=>'root','DB_PASS'=>'','DB_NAME'=>'autism_support_system'];
   ```
5. Log in with the demo account `demo@parentconnect.test` / `Demo@1234`, or sign up.
   (Delete the demo user before going public.)

## Deploy on Vercel
See **[DEPLOY_VERCEL.md](DEPLOY_VERCEL.md)** for the step-by-step guide.

## Themes
The sun/half-circle button in the top bar cycles **Light → Dark → Calm**. *Calm* is a low-stimulation palette with no animations or shadows, useful for sensory-sensitive users. The choice is remembered in the browser.
