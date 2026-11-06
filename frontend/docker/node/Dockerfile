FROM node:20-bookworm-slim

# bookworm-slim y no alpine: Next 16 usa binarios nativos (SWC, lightningcss)
# que en musl dan problemas.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

ENV NEXT_TELEMETRY_DISABLED=1

COPY docker/node/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

EXPOSE 3000

ENTRYPOINT ["entrypoint"]
CMD ["npm", "run", "dev"]
