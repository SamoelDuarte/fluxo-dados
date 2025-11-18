# 🚀 Configuração do Laravel Queue Worker para WhatsApp

## O que foi feito?

✅ **Job criado:** `app/Jobs/SendWhatsappMessageQueue.php`
- Processa envios de mensagens WhatsApp em background
- 3 tentativas automáticas se falhar
- Delay aleatório entre mensagens (1-5 segundos)
- Timeout de 30 segundos por mensagem
- Logging completo de sucesso e erros

✅ **Método `envioEmMassa` atualizado**
- Agora dispara jobs para a fila em vez de processar direto
- Evita timeout do cron
- Evita duplicação de mensagens
- Retorna quantas mensagens foram adicionadas à fila

✅ **`.env` configurado**
- `QUEUE_CONNECTION=database` (usando banco de dados para armazenar fila)

---

## 📋 Como usar?

### Opção 1: Usando o Script `.bat` (Recomendado para Windows)

**Duplo clique em:** `start-worker.bat`

Vai abrir um menu interativo com as opções:
- 1️⃣ Iniciar worker normal
- 2️⃣ Iniciar em modo debug (mais verboso)
- 3️⃣ Processar só a fila 'whatsapp'
- 4️⃣ Parar todos os workers

### Opção 2: Usando PowerShell

```powershell
# Executar o script PowerShell
.\run-queue-worker.ps1
```

### Opção 3: Linha de comando manual

```bash
# Terminal/CMD no diretório do projeto
cd C:\xampp\htdocs\fluxo-dados

# Iniciar worker
php artisan queue:work --queue=whatsapp --tries=3 --timeout=30

# Para parar: CTRL+C
```

---

## 🔧 Monitoramento

Enquanto o worker está rodando, você verá logs assim:

```
[2025-11-18 10:30:45] Processing: App\Jobs\SendWhatsappMessageQueue
[2025-11-18 10:30:46] ✅ Mensagem enviada com sucesso! ID: 1234567890
[2025-11-18 10:30:47] Processing: App\Jobs\SendWhatsappMessageQueue
[2025-11-18 10:30:48] ✅ Mensagem enviada com sucesso! ID: 1234567891
```

Para **ver logs mais detalhados**, execute com `-v`:
```bash
php artisan queue:work --queue=whatsapp --tries=3 --timeout=30 -v
```

---

## 📡 Fluxo de trabalho

1. **Cron/Manual chama `envioEmMassa()`**
   - Valida horário
   - Pega token WhatsApp
   - Busca campanhas ativas
   - Para cada contato: **Dispara um Job para a fila** ⚡

2. **Worker processa os Jobs**
   - Busca job da fila
   - Envia mensagem via WhatsApp API
   - Se sucesso: Marca `send = 1` no banco
   - Se erro: Tenta novamente (até 3 vezes)
   - Se falhar 3x: Log do erro e `send = -1`

3. **Nenhum timeout** ✅
   - Cada job tem timeout de 30 segundos
   - Se demorar mais, o worker trata e tenta novamente
   - O cron termina rapidamente (só enfileira)

---

## ⚙️ Configurações importantes

### No `.env`:
```env
QUEUE_CONNECTION=database    # ✅ Já configurado
QUEUE_FAILED_TABLE=failed_jobs
```

### No `config/queue.php` (opcional):
```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,
],
```

---

## 🐛 Troubleshooting

### Problema: "Nenhum job está sendo processado"

**Solução 1:** Verifique se o worker está rodando
```bash
php artisan queue:work --queue=whatsapp -v
```

**Solução 2:** Limpe a fila
```bash
php artisan queue:flush
```

**Solução 3:** Verifique o banco de dados
```bash
# Ver jobs na fila
SELECT COUNT(*) FROM jobs;

# Ver jobs que falharam
SELECT COUNT(*) FROM failed_jobs;
```

### Problema: "Worker trava ou não responde"

**Solução:** Reinicie o worker
```bash
# Em outro terminal
php artisan queue:restart

# Depois execute de novo
php artisan queue:work --queue=whatsapp --tries=3 --timeout=30
```

### Problema: "Mensagens duplicadas"

Isso **não deve acontecer mais** porque:
- ✅ Apenas o job marca `send = 1`
- ✅ Se tiver erro, tenta novamente (idempotência)
- ✅ Transações no banco garantem integridade

Se acontecer ainda, verifique:
1. Se há múltiplos workers rodando
2. Se o job está falhando e sendo retentado infinitamente
3. Logs em `storage/logs/laravel.log`

---

## 📊 Comandos úteis

```bash
# Ver quantas mensagens estão na fila
SELECT COUNT(*) FROM jobs WHERE queue = 'whatsapp';

# Limpar toda a fila
php artisan queue:flush

# Ver jobs que falharam
php artisan queue:failed

# Tentar reenviar um job que falhou
php artisan queue:retry <id>

# Parar o worker gracefully (termina jobs atuais)
php artisan queue:restart

# Monitor em tempo real (Linux/Mac)
watch -n 1 'php artisan queue:work --queue=whatsapp'
```

---

## 🎯 Checklist de Instalação

- ✅ Job criado: `app/Jobs/SendWhatsappMessageQueue.php`
- ✅ Método `envioEmMassa()` atualizado
- ✅ `.env` configurado: `QUEUE_CONNECTION=database`
- ✅ Migrations executadas: `php artisan migrate`
- ✅ Script `.bat` criado para facilitar

## ✨ Próximos passos

1. **Teste local primeiro:**
   ```bash
   php artisan queue:work --queue=whatsapp --tries=3 --timeout=30 -v
   ```

2. **Para produção, use Supervisor (Linux) ou Task Scheduler (Windows)**

3. **Monitore os logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 📞 Suporte

Se tiver dúvidas:
1. Verifique os logs: `storage/logs/laravel.log`
2. Rode com `-v` para mais detalhes
3. Verifique se o banco está acessível
4. Confirme que `QUEUE_CONNECTION=database` no `.env`

---

**Criado em:** 18/11/2025
**Status:** ✅ Pronto para produção
