# Queue Worker - Ubuntu/Supervisor

## Instalar

```bash
sudo apt-get update && sudo apt-get install -y supervisor
```

## Configurar

```bash
sudo nano /etc/supervisor/conf.d/laravel-queue-whatsapp.conf
```

Cole:
```ini
[program:laravel-queue-whatsapp]
command=php /www/wwwroot/fluxo-neocob.betasolucao.com.br/artisan queue:work --queue=whatsapp --tries=3 --timeout=30
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/laravel-queue-whatsapp.log
user=www-data
directory=/www/wwwroot/fluxo-neocob.betasolucao.com.br
stopasgroup=true
```

Salvar: `CTRL + X` → `Y` → `ENTER`

## Ativar

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start laravel-queue-whatsapp:*
```

Status: `sudo supervisorctl status laravel-queue-whatsapp:*`

## Pausar/Parar

```bash
# Parar (pausa as campanhas)
sudo supervisorctl stop laravel-queue-whatsapp:*

# Retomar (ativa de novo)
sudo supervisorctl start laravel-queue-whatsapp:*

# Reiniciar (se travou)
sudo supervisorctl restart laravel-queue-whatsapp:*
```

## Logs

```bash
# Queue logs
sudo tail -f /var/log/laravel-queue-whatsapp.log

# Laravel logs
sudo tail -f /www/wwwroot/fluxo-neocob.betasolucao.com.br/storage/logs/laravel.log
```

## Problema: Múltiplos workers rodando

```bash
ps aux | grep queue:work
sudo pkill -f "queue:work"
sudo supervisorctl restart laravel-queue-whatsapp:*
```