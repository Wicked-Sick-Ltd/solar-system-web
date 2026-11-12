# S3 (Ceph RGW) for OG share-card storage

The app caches rendered per-object Open Graph cards on a Storage disk. In
production that disk is `s3`, backed by the Wicked Sick Ceph RGW service at
`https://s3.wickedsick.com`. The app only ever reads/writes keys under `og/`,
and it **serves the bytes itself** — so the bucket stays private.

This is the app-specific summary. The authoritative procedures live in the
Engineering Wiki → Infrastructure → *S3 Object Storage (Ceph RGW)* (Architecture
+ the two Operations runbooks). The steps below follow the "Add Users & Buckets"
runbook for the existing **`wizmedia`** account.

## What to provision

| Item | Value |
|------|-------|
| Account | `wizmedia` (existing) |
| App user | `solar-system-web` |
| Bucket | `solar-system-web` |
| Endpoint | `https://s3.wickedsick.com` (vhost-style) |

## One-time setup (account **root** keys, no cluster access needed)

```bash
# Root profile (one endpoint covers s3 + iam)
aws --profile wizroot configure set endpoint_url https://s3.wickedsick.com
aws --profile wizroot configure set region default
aws --profile wizroot configure set aws_access_key_id     <WIZMEDIA_ROOT_KEY>
aws --profile wizroot configure set aws_secret_access_key <WIZMEDIA_ROOT_SECRET>

# 1. Create the scoped app user and mint its keys (these go in the app .env)
aws --profile wizroot iam create-user --user-name solar-system-web
aws --profile wizroot iam create-access-key --user-name solar-system-web

# 2. Attach a scoped identity policy: full access to its one bucket, but it
#    may not delete the bucket. (No `Principal` field — identity policy.)
cat > solar-policy.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    { "Sid": "FullAccessToOwnBucket", "Effect": "Allow", "Action": ["s3:*"],
      "Resource": ["arn:aws:s3:::solar-system-web", "arn:aws:s3:::solar-system-web/*"] },
    { "Sid": "NoDeleteBucket", "Effect": "Deny", "Action": ["s3:DeleteBucket"],
      "Resource": ["arn:aws:s3:::solar-system-web"] }
  ]
}
EOF
aws --profile wizroot iam put-user-policy \
  --user-name solar-system-web \
  --policy-name solar-bucket-access \
  --policy-document file://solar-policy.json

# 3. Pre-create the bucket. Laravel's S3 driver does NOT auto-create it, so make
#    it once (either profile works; the policy allows the app user to as well).
aws --profile wizroot s3 mb s3://solar-system-web
```

## App configuration (`.env`)

```dotenv
OG_DISK=s3
AWS_ACCESS_KEY_ID=<solar-system-web access key>
AWS_SECRET_ACCESS_KEY=<solar-system-web secret key>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=solar-system-web
AWS_ENDPOINT=https://s3.wickedsick.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

In dev/CI leave `OG_DISK=local` — cards cache to `storage/app` and nothing else
is needed. Requires the **imagick** PHP extension in every environment that
renders cards.

## Verify

```bash
# After deploy, hit a card and confirm a PNG comes back, then that it landed in S3:
curl -sI https://publicuniverse.net/og/objects/planet-saturn.png | grep -i content-type
aws --profile wizroot s3 ls s3://solar-system-web/og/ --recursive | head
```

## Rotate / revoke

```bash
aws --profile wizroot iam list-access-keys  --user-name solar-system-web
aws --profile wizroot iam create-access-key --user-name solar-system-web   # add new, update .env
aws --profile wizroot iam delete-access-key --user-name solar-system-web --access-key-id <OLD_KEY_ID>
```
