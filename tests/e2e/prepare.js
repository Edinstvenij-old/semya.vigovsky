// Fresh database with the demo family before the e2e run (env comes from playwright.config.js).
import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';

writeFileSync('database/e2e.sqlite', '');
execSync('php artisan migrate:fresh --seed --force', { stdio: 'inherit' });
