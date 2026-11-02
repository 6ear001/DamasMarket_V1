#!/usr/bin/env bash
# Startet die Seite in GitHub Codespaces (Port 8000) und zeigt den Installationscode für /admin/.
cd "$(dirname "$0")/.." || exit 1
mkdir -p app/data public/uploads
pkill -f "php -S 0.0.0.0:8000" 2>/dev/null
nohup php -S 0.0.0.0:8000 -t public > /tmp/damas-server.log 2>&1 &
sleep 1
curl -sL -o /dev/null http://localhost:8000/admin/   # legt Datenbank und Installationscode an
echo
echo "================================================================"
echo " Damas Market läuft auf Port 8000."
echo " Öffne den Reiter „Ports“ → Adresse von Port 8000 → hänge /admin/ an."
if [ -f app/data/setup-code.txt ]; then
  echo " Installationscode für die erste Einrichtung: $(cat app/data/setup-code.txt)"
else
  echo " Admin ist schon eingerichtet – ganz normal anmelden."
fi
echo "================================================================"
