# Build-time networking installs only distribution packages. Render with --network none.
FROM debian:bookworm-slim@sha256:3783cc01769c7b2b1b83a5c5ad96c815348e28ed7da68e2e3687004faa906251
RUN apt-get update \
    && apt-get install -y --no-install-recommends wkhtmltopdf poppler-utils fonts-crosextra-carlito fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*
ENV QT_QPA_PLATFORM=offscreen
WORKDIR /work
