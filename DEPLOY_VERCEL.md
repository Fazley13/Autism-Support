# Deploying ParentConnect on Vercel (PHP + MySQL)

## Why XAMPP's database can't be used
XAMPP's MySQL lives on your own computer (`localhost`). Vercel runs your code on its servers, which cannot reach
your laptop. So you need a **MySQL database in the cloud**, and Vercel only runs the PHP code.

Vercel also has a **read-only disk** and runs each request on a short-lived instance. This project is already adapted:
* sessions are stored in the database (`php_sessions` table), so login works across instances;
* uploaded images are stored in the database (`media` table), not on disk;
* all settings come from environment variables.

## Step 1 – Create a cloud MySQL database (free options)
Any of these works – pick one:
* **TiDB Cloud Serverless** (MySQL-compatible, free tier) – https://tidbcloud.com
* **Aiven for MySQL** (free plan) – https://aiven.io
* **Railway / Clever Cloud / FreeSQLDatabase** – small MySQL instances

Copy the **host, port, user, password, database name**. Most providers require SSL → use `DB_SSL=1`.

## Step 2 – Import the data
Use phpMyAdmin / MySQL Workbench / DBeaver (or the `mysql` CLI) connected to the cloud database and import
`database/autism_support_system.sql`.
```bash
mysql -h HOST -P PORT -u USER -p --ssl-mode=REQUIRED DBNAME < database/autism_support_system.sql
```
If you already imported the *old* dump earlier, import only `database/upgrade_v2.sql` instead (run it once).

## Step 3 – Push the project to GitHub
```bash
git init && git add . && git commit -m "ParentConnect" 
git branch -M main && git remote add origin https://github.com/<you>/Autism-Support.git && git push -u origin main
```

## Step 4 – Import into Vercel
1. https://vercel.com/new → import the repository. Framework preset: **Other**. Leave build settings (they come from `vercel.json`).
2. **Settings → Environment Variables** (see `.env.example`):

| Name | Example |
|---|---|
| `DB_HOST` | `gateway01.ap-southeast-1.prod.aws.tidbcloud.com` |
| `DB_PORT` | `4000` (TiDB) / `3306` / provider port |
| `DB_USER` | your user |
| `DB_PASS` | your password |
| `DB_NAME` | your database name |
| `DB_SSL` | `1` |
| `APP_DEBUG` | `1` only while debugging – then remove |

3. Deploy. Open the Vercel URL – the landing page should appear; log in with `demo@parentconnect.test` / `Demo@1234`
   (delete this account afterwards: `DELETE FROM user WHERE email='demo@parentconnect.test';`).

## How it works
`vercel.json` uses the community **`vercel-php@0.7.4`** runtime (PHP 8.3). Every request is routed to `api/index.php`, which runs the
matching PHP file (`/modules/social/newsfeed.php` etc.). The build step copies `assets/` and `uploads/` into `public/`
so Vercel serves them as static files.

## Troubleshooting
* **"The database is not reachable"** – wrong env vars, or the DB firewall blocks Vercel (allow all IPs / 0.0.0.0/0).
* **500 error** – set `APP_DEBUG=1`, redeploy, read the message (also visible in Vercel → Logs).
* **Styles / logo missing** – check the deployment log that the `buildCommand` ran (it creates `public/`).
* **Upload fails** – images must be JPG/PNG/GIF/WebP up to 3 MB (Vercel's request limit is 4.5 MB).
* The community `vercel-php` runtime is not an official Vercel product. If you ever need a more conventional host,
  this project also runs unchanged on any PHP host (Railway/Render with Docker, shared hosting, VPS) – just set the same variables.
