-- =============================================================================
-- SCRIPT DE TESTE - ENVIO EM MASSA DE CAMPANHAS
-- =============================================================================
-- Este script cria dados de teste para validar a funcionalidade de envio em massa
-- Executar em ordem antes de testar o endpoint /api/envio-em-massa
-- =============================================================================

-- 1. LIMPAR DADOS ANTERIORES (OPCIONAL)
-- Descomente se quiser resetar os dados de teste
/*
DELETE FROM campanha_telefone;
DELETE FROM campanha_contato;
DELETE FROM contato_dados;
DELETE FROM campanhas;
DELETE FROM telefones;
DELETE FROM contatos;
DELETE FROM available_slots;
*/

-- =============================================================================
-- 2. CRIAR SLOT DE DISPONIBILIDADE
-- =============================================================================
-- Cria um horário para hoje que permite envio
INSERT INTO available_slots (day_of_week, start_time, end_time, created_at, updated_at)
SELECT 
    CASE DAYOFWEEK(NOW())
        WHEN 1 THEN 'domingo'
        WHEN 2 THEN 'segunda'
        WHEN 3 THEN 'terça'
        WHEN 4 THEN 'quarta'
        WHEN 5 THEN 'quinta'
        WHEN 6 THEN 'sexta'
        WHEN 7 THEN 'sábado'
    END as day_of_week,
    '00:00:00' as start_time,
    '23:59:59' as end_time,
    NOW() as created_at,
    NOW() as updated_at
WHERE NOT EXISTS (
    SELECT 1 FROM available_slots 
    WHERE day_of_week = CASE DAYOFWEEK(NOW())
        WHEN 1 THEN 'domingo'
        WHEN 2 THEN 'segunda'
        WHEN 3 THEN 'terça'
        WHEN 4 THEN 'quarta'
        WHEN 5 THEN 'quinta'
        WHEN 6 THEN 'sexta'
        WHEN 7 THEN 'sábado'
    END
);

-- =============================================================================
-- 3. CRIAR TELEFONES
-- =============================================================================
-- Cria 2 telefones para teste (linhas WhatsApp)
INSERT INTO telefones (phone_number, created_at, updated_at)
VALUES 
    ('551132334455', NOW(), NOW()),  -- Telefone 1
    ('551142445566', NOW(), NOW());  -- Telefone 2

-- Armazenar IDs dos telefones (notar: provavelmente 1 e 2, ou adaptar conforme necessário)
SET @telefone_id_1 = (SELECT id FROM telefones WHERE phone_number = '551132334455' LIMIT 1);
SET @telefone_id_2 = (SELECT id FROM telefones WHERE phone_number = '551142445566' LIMIT 1);

-- =============================================================================
-- 4. CRIAR CONTATOS
-- =============================================================================
-- Cria 5 contatos para teste
INSERT INTO contatos (nome, created_at, updated_at)
VALUES 
    ('João Silva', NOW(), NOW()),
    ('Maria Santos', NOW(), NOW()),
    ('Pedro Oliveira', NOW(), NOW()),
    ('Ana Costa', NOW(), NOW()),
    ('Carlos Mendes', NOW(), NOW());

-- Armazenar IDs dos contatos
SET @contato_1 = (SELECT id FROM contatos WHERE nome = 'João Silva' LIMIT 1);
SET @contato_2 = (SELECT id FROM contatos WHERE nome = 'Maria Santos' LIMIT 1);
SET @contato_3 = (SELECT id FROM contatos WHERE nome = 'Pedro Oliveira' LIMIT 1);
SET @contato_4 = (SELECT id FROM contatos WHERE nome = 'Ana Costa' LIMIT 1);
SET @contato_5 = (SELECT id FROM contatos WHERE nome = 'Carlos Mendes' LIMIT 1);

-- =============================================================================
-- 5. CRIAR CAMPANHAS
-- =============================================================================
-- Cria 2 campanhas ativas para teste

-- Campanha 1: Sem imagem
INSERT INTO campanhas (name, status, mensagem, created_at, updated_at)
VALUES (
    'Promoção Especial',
    'playing',
    'Olá! Aproveite nossa promoção especial com até 50% de desconto. Visite nosso site: https://exemplo.com.br',
    NOW(),
    NOW()
);

-- Campanha 2: Com imagem (caso tenha imagens cadastradas)
INSERT INTO campanhas (name, status, mensagem, img_campanha, created_at, updated_at)
SELECT 
    'Campanha Produto Novo',
    'playing',
    'Conheça nosso novo produto! Clique no link abaixo para saber mais.',
    id,
    NOW(),
    NOW()
FROM imagens_campanha
LIMIT 1;

-- Alternativa (sem imagem se não existir):
INSERT INTO campanhas (name, status, mensagem, created_at, updated_at)
VALUES (
    'Campanha Produto Novo',
    'playing',
    'Conheça nosso novo produto! Clique no link abaixo para saber mais.',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE id = id;

-- Armazenar IDs das campanhas
SET @campanha_1 = (SELECT id FROM campanhas WHERE name = 'Promoção Especial' LIMIT 1);
SET @campanha_2 = (SELECT id FROM campanhas WHERE name = 'Campanha Produto Novo' LIMIT 1);

-- =============================================================================
-- 6. ASSOCIAR TELEFONES ÀS CAMPANHAS
-- =============================================================================
-- Cada campanha precisa de um telefone associado

-- Campanha 1 → Telefone 1
INSERT INTO campanha_telefone (campanha_id, telefone_id, created_at, updated_at)
VALUES (@campanha_1, @telefone_id_1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Campanha 2 → Telefone 2
INSERT INTO campanha_telefone (campanha_id, telefone_id, created_at, updated_at)
VALUES (@campanha_2, @telefone_id_2, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =============================================================================
-- 7. ASSOCIAR CONTATOS ÀS CAMPANHAS
-- =============================================================================
-- Cada contato deve estar associado à campanha

-- Campanha 1 - Contatos 1, 2, 3
INSERT INTO campanha_contato (campanha_id, contato_id, created_at, updated_at)
VALUES 
    (@campanha_1, @contato_1, NOW(), NOW()),
    (@campanha_1, @contato_2, NOW(), NOW()),
    (@campanha_1, @contato_3, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Campanha 2 - Contatos 3, 4, 5
INSERT INTO campanha_contato (campanha_id, contato_id, created_at, updated_at)
VALUES 
    (@campanha_2, @contato_3, NOW(), NOW()),
    (@campanha_2, @contato_4, NOW(), NOW()),
    (@campanha_2, @contato_5, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =============================================================================
-- 8. CRIAR DADOS DE CONTATO (contato_dados)
-- =============================================================================
-- Dados de contato não enviados (send = 0) para teste

INSERT INTO contato_dados (contato_id, telefone, send, created_at, updated_at)
VALUES 
    (@contato_1, '11987654321', 0, NOW(), NOW()),
    (@contato_2, '11988776655', 0, NOW(), NOW()),
    (@contato_3, '11989998877', 0, NOW(), NOW()),
    (@contato_4, '11991112222', 0, NOW(), NOW()),
    (@contato_5, '11992223333', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    send = CASE WHEN send = 1 THEN 1 ELSE 0 END,
    updated_at = NOW();

-- =============================================================================
-- 9. VERIFICAÇÃO DOS DADOS CRIADOS
-- =============================================================================

-- Verificar slots disponíveis
SELECT '=== SLOTS DISPONÍVEIS ===' as info;
SELECT * FROM available_slots WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Verificar telefones
SELECT '=== TELEFONES ===' as info;
SELECT id, phone_number FROM telefones ORDER BY id DESC LIMIT 5;

-- Verificar campanhas ativas
SELECT '=== CAMPANHAS ATIVAS ===' as info;
SELECT id, name, status, mensagem FROM campanhas WHERE status = 'playing';

-- Verificar contatos
SELECT '=== CONTATOS ===' as info;
SELECT id, nome FROM contatos ORDER BY id DESC LIMIT 5;

-- Verificar dados de contato não enviados
SELECT '=== DADOS DE CONTATO (NÃO ENVIADOS) ===' as info;
SELECT id, contato_id, telefone, send, telefone_id FROM contato_dados 
WHERE send = 0 
ORDER BY id DESC 
LIMIT 10;

-- Verificar associações
SELECT '=== CAMPANHA → TELEFONE ===' as info;
SELECT ct.campanha_id, ct.telefone_id, c.name, t.phone_number 
FROM campanha_telefone ct
JOIN campanhas c ON c.id = ct.campanha_id
JOIN telefones t ON t.id = ct.telefone_id
ORDER BY ct.campanha_id;

SELECT '=== CAMPANHA → CONTATO ===' as info;
SELECT ct.campanha_id, ct.contato_id, c.name, co.nome 
FROM campanha_contato ct
JOIN campanhas c ON c.id = ct.campanha_id
JOIN contatos co ON co.id = ct.contato_id
ORDER BY ct.campanha_id;

-- =============================================================================
-- 10. COMANDOS PARA TESTE
-- =============================================================================

/*
APÓS EXECUTAR ESTE SCRIPT:

1. Verificar dados inseridos:
   SELECT * FROM available_slots;
   SELECT * FROM campanhas WHERE status = 'playing';
   SELECT * FROM contato_dados WHERE send = 0;

2. Testar o endpoint:
   curl http://localhost:8000/api/envio-em-massa

3. Verificar resultado:
   SELECT * FROM contato_dados WHERE send = 1;
   SELECT COUNT(*) as total_enviados FROM contato_dados WHERE send = 1;
   SELECT COUNT(*) as total_nao_enviados FROM contato_dados WHERE send = 0;

4. Verificar logs de erro:
   tail -f storage/logs/laravel.log | grep "Erro ao enviar mensagem"

5. Limpar dados de teste (se necessário):
   DELETE FROM campanha_telefone WHERE campanha_id IN (SELECT id FROM campanhas WHERE status = 'playing');
   DELETE FROM campanha_contato WHERE campanha_id IN (SELECT id FROM campanhas WHERE status = 'playing');
   DELETE FROM contato_dados WHERE contato_id IN (SELECT id FROM contatos WHERE nome LIKE '%');
   DELETE FROM campanhas WHERE status = 'playing';
   DELETE FROM contatos WHERE nome IN ('João Silva', 'Maria Santos', 'Pedro Oliveira', 'Ana Costa', 'Carlos Mendes');
   DELETE FROM telefones WHERE phone_number IN ('551132334455', '551142445566');
*/

-- =============================================================================
-- FIM DO SCRIPT DE TESTE
-- =============================================================================
