#!/usr/bin/env bash
# =====================================================================
# eClinicPro — server-side deploy / diagnose script
# Run this over SSH on the cPanel server, NOT on your laptop:
#     ssh silverwebbuzz_in@silverwebbuzz.in -p 22
#     cd /home/silverwebbuzz_in/public_html/eclinicpro
#     bash deploy.sh
#
# It does what cPanel's "Update" + "Deploy" buttons should do, but
# reliably: shows you the real state, pulls main, and clears the PHP
# OPcache so new code actually shows up on the live site.
# =====================================================================
set -uo pipefail

REPO="/home/silverwebbuzz_in/public_html/eclinicpro"
BRANCH="main"

cd "$REPO" || { echo "FATAL: $REPO not found"; exit 1; }

echo "================ 1. CURRENT STATE ================"
echo "Folder        : $(pwd)"
echo "Branch        : $(git rev-parse --abbrev-ref HEAD)"
echo "Local HEAD    : $(git log -1 --format='%h %s')"
echo ""
echo "Uncommitted changes on the server (these BLOCK a clean pull):"
git status --porcelain
echo "--------------------------------------------------"

echo ""
echo "================ 2. FETCH REMOTE ================="
git fetch origin --prune
echo "Remote $BRANCH : $(git log -1 --format='%h %s' origin/$BRANCH)"
BEHIND=$(git rev-list --count HEAD..origin/$BRANCH)
echo "Commits the server is BEHIND origin/$BRANCH: $BEHIND"

if [ "$BEHIND" -eq 0 ]; then
  echo ">> Server already has the latest code. If the site still looks old,"
  echo ">> the problem is CACHE, not git. Skip to step 4 (opcache reset)."
fi
echo "--------------------------------------------------"

echo ""
echo "================ 3. PULL latest main ============="
# If there are local edits made directly on the server, stash them so the
# pull succeeds. They are saved (not lost) and can be inspected with:
#     git stash list   /   git stash show -p
if [ -n "$(git status --porcelain)" ]; then
  echo "Server has local edits — stashing them so pull can proceed."
  git stash push -u -m "auto-stash-before-deploy-$(date +%F-%H%M)"
fi

git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"
echo "New HEAD      : $(git log -1 --format='%h %s')"
echo "--------------------------------------------------"

echo ""
echo "================ 4. CLEAR PHP OPCACHE ============"
# Stale OPcache is the #1 reason 'I pulled but the site looks the same'.
PHP_BIN="$(command -v php || echo /usr/local/bin/php)"
"$PHP_BIN" -r 'if(function_exists("opcache_reset")){opcache_reset();echo "opcache_reset() OK\n";}else{echo "opcache not enabled for CLI php\n";}'
echo ">> If the CLI php has a different OPcache than the web PHP-FPM,"
echo ">> also do: cPanel -> Restart Services -> PHP-FPM  (or touch a .php file)."
echo "--------------------------------------------------"

echo ""
echo "================ 5. DONE ========================="
echo "Live folder now at commit: $(git log -1 --format='%h %s')"
echo "Open the site in a PRIVATE/incognito window to bypass the browser cache."
