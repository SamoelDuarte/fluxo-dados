# Configurar Queue Worker no Ubuntu (Supervisor)

## ⚠️ IMPORTANTE: Evitar Duplicação de Mensagens

O worker **NUNCA** pode rodar 2x simultâneamente, senão vai enviar mensagens duplicadas. Este guia garante que apenas 1 worker rode por vez.

---

## Passo 1: Instalar Supervisor

```bash
sudo apt-get update
sudo apt-get install -y supervisor
```

---

## Passo 2: Criar Arquivo de Configuração

Crie o arquivo `/etc/supervisor/conf.d/laravel-queue-whatsapp.conf`:

```bash
sudo nano /etc/supervisor/conf.d/laravel-queue-whatsapp.conf
```

Cole o conteúdo abaixo:

```ini
[program:laravel-queue-whatsapp]
process_name=%(program_name)s_%(process_num)02d
command=php /www/wwwroot/fluxo-neocob.betasolucao.com.br/artisan queue:work --queue=whatsapp --tries=3 --timeout=30
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/laravel-queue-whatsapp.log
user=www-data
directory=/www/wwwroot/fluxo-neocob.betasolucao.com.br
stopasgroup=true
killasgroup=true
```

**Seu caminho já está configurado acima:**
```
/www/wwwroot/fluxo-neocob.betasolucao.com.br
```

---

## Passo 3: Salvar e Sair

Pressione `CTRL + X` → `Y` → `ENTER`

---

## Passo 4: Recarregar Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

---

## Passo 5: Iniciar o Worker

```bash
sudo supervisorctl start laravel-queue-whatsapp:*
```

---

## Passo 6: Verificar Status

```bash
sudo supervisorctl status laravel-queue-whatsapp:*
```

Você deve ver algo como:
```
laravel-queue-whatsapp:laravel-queue-whatsapp_00   RUNNING   pid 12345, uptime 0:00:05
```

---

## 🔍 Monitorar Logs

```bash
# Ver logs em tempo real
sudo tail -f /var/log/laravel-queue-whatsapp.log

# Ver logs do Laravel
sudo tail -f /www/wwwroot/fluxo-neocob.betasolucao.com.br/storage/logs/laravel.log
```

---

## ⏹️ Parar o Worker

```bash
sudo supervisorctl stop laravel-queue-whatsapp:*
```

---

## 🔄 Reiniciar o Worker

```bash
sudo supervisorctl restart laravel-queue-whatsapp:*
```

---

## ✅ Verificar se Está Funcionando

1. **Enqueue uma mensagem:**
   ```bash
   cd /www/wwwroot/fluxo-neocob.betasolucao.com.br
   php artisan tinker
   ```

   ```php
   DB::table('contato_dados')->where('send', 0)->limit(1)->first();
   // Se não houver dados, crie um teste
   ```

2. **Ver se o worker processou:**
   ```bash
   sudo tail -f /var/log/laravel-queue-whatsapp.log
   ```

   Você deve ver logs como:
   ```
   📤 [Job Queue] Enviando para: 5541999999999 (João)
   ✅ Mensagem enviada com sucesso! ID: wamid_xxx
   ```

---

## 🛡️ Garantir que NÃO RODA 2X

A configuração acima garante:

1. **`numprocs=1`** → Apenas 1 worker rodando
2. **`autorestart=true`** → Se cair, reinicia sozinho
3. **`stopasgroup=true`** → Para todos os processos corretamente

**Verificar se tem outros workers rodando:**
```bash
ps aux | grep queue:work
```

Se aparecer mais de 1 processo `queue:work`, mate todos:
```bash
sudo pkill -f "queue:work"
sudo supervisorctl restart laravel-queue-whatsapp:*
```

---

## 📋 Checklist Final

- [ ] Supervisor instalado
- [ ] Arquivo de configuração criado em `/etc/supervisor/conf.d/`
- [ ] Caminho do projeto atualizado corretamente
- [ ] `supervisorctl reread` executado
- [ ] `supervisorctl update` executado
- [ ] Worker iniciado com `start laravel-queue-whatsapp:*`
- [ ] Status mostra `RUNNING`
- [ ] Logs aparecem em `/var/log/laravel-queue-whatsapp.log`

---

## 🆘 Troubleshooting

### Worker não inicia
```bash
sudo supervisorctl tail laravel-queue-whatsapp:laravel-queue-whatsapp_00
```

### Permissão negada
```bash
sudo chown -R www-data:www-data /www/wwwroot/fluxo-neocob.betasolucao.com.br
```

### Logs vazios
Verifique se o caminho do projeto está correto:
```bash
php /www/wwwroot/fluxo-neocob.betasolucao.com.br/artisan queue:work --queue=whatsapp --tries=3 --timeout=30
```

---

## 🚀 Pronto!

Seu worker agora roda 24/7 no Ubuntu sem terminal aberto e sem duplicação de mensagens! ✅

<!-- enviar acordo apara neocob -->
<!-- php artisan acordos:send-datacob -->

<!-- comecar o envio php artisan queue:work --queue=default -->
<!-- php artisan queue:work --queue=default -->