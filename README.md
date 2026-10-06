# VisionCar Backend

Backend para landing page de Ordens de Serviço.

## Endpoints

- `GET /nota/{os}-{token}` → Landing page da nota
- `POST /upload` → Recebe upload do app e devolve o link
- `GET /ping` → Endpoint pro UptimeRobot

## Variáveis de ambiente (Render)

- `UPSTASH_REDIS_REST_URL` → URL do Redis
- `UPSTASH_REDIS_REST_TOKEN` → Token do Redis

## Deploy

1. Sobe pro GitHub
2. Conecta no Render
3. Configura as variáveis
4. Pronto
