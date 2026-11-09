# Deployment

- No se usa Laravel Cloud. Se despliega en un servidor on-premise con Docker.
- Flujo:
  1. En el PC, construir la imagen de producción (`Dockerfile.prod`) con su versión: `IMAGE=ghcr.io/luisolavubo/api-iam:<versión> docker compose -f docker-compose.prod.yml build app`.
  2. Publicarla en GitHub Packages: `docker push ghcr.io/luisolavubo/api-iam:<versión>`.
  3. En el servidor, con `IMAGE` en su `.env`: `docker compose -f docker-compose.prod.yml pull` y `docker compose -f docker-compose.prod.yml up -d --no-build`.
- Las migraciones y los comandos `iam:*` se ejecutan a mano, nunca desde el entrypoint ni desde CI.
- El detalle está en la sección "Despliegue" del README.
