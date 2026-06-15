# 배포 가이드 (EC2 + Docker Compose + Caddy)

`diary.viram.dev` 에 올리는 절차. 스택은 **app(php-fpm) + web(Caddy 자동 HTTPS)**,
DB는 SQLite(파일 하나), 이미지/DB는 호스트에 남아 백업이 쉽다.

---

## 0. 사전 준비 (한 번만)

### EC2 보안 그룹 (인바운드)
| 포트 | 소스 | 용도 |
|------|------|------|
| 22   | 내 IP만 | SSH |
| 80   | 0.0.0.0/0 | HTTP(인증서 발급 + 리다이렉트) |
| 443  | 0.0.0.0/0 | HTTPS |

### Docker 설치 (Ubuntu 기준)
```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
# 로그아웃 후 재접속 (그룹 적용)
```

### Cloudflare DNS
- `diary` A 레코드 → **EC2 퍼블릭 IP**
- **Proxy status = DNS only (회색 구름)** 으로 둘 것.
  주황 구름(프록시)이면 Caddy의 Let's Encrypt 발급(80번 챌린지)이 막힌다.
  (나중에 프록시를 켜고 싶으면 Cloudflare Origin 인증서 방식으로 전환 — 아래 참고)

---

## 1. 최초 배포

```bash
git clone <레포 URL> ootd && cd ootd

cp .env.production.example .env
# .env 편집: GEMINI_API_KEY 입력, APP_URL 확인,
#            id -u / id -g 가 1000이 아니면 APP_UID/APP_GID 수정
nano .env

# APP_KEY 생성
docker compose -f compose.prod.yaml run --rm app php artisan key:generate

# 배포
chmod +x deploy.sh
./deploy.sh
```

`https://diary.viram.dev` 접속 → 첫 인증서 발급에 10~30초 걸릴 수 있다.

---

## 2. 이후 업데이트

코드를 main에 푸시한 뒤 EC2에서:
```bash
./deploy.sh
```
`git pull → build → composer install → migrate → optimize → up` 을 자동 수행한다.

---

## 3. 백업

중요한 건 딱 두 가지 (둘 다 호스트에 있음):
```bash
# DB
cp database/database.sqlite ~/backups/ootd-$(date +%F).sqlite
# 생성된 아바타
tar czf ~/backups/avatars-$(date +%F).tgz storage/app/public/avatars
```
크론으로 S3에 올려두면 안전하다.

---

## 4. 운영 메모

- **로그**: `docker compose -f compose.prod.yaml logs -f web` (Caddy/인증서), `... logs -f app` (PHP)
- **재시작**: `docker compose -f compose.prod.yaml restart`
- **아바타 생성이 동기 처리**라 요청 한 건이 Gemini 응답(10~20초)을 잡고 있는다.
  1인용이라 지금은 문제없고 타임아웃(php 120s / Caddy 180s)도 넉넉하다.
  트래픽이 늘면 `OutfitController` 의 생성 호출을 큐 잡으로 빼는 게 다음 단계.
- **인스턴스 사양**: SQLite라 DB 서버가 없어 t3.small(2GB)면 여유. t3.micro도 동작하나 빌드 시 빡빡할 수 있다.

### Cloudflare 프록시(주황 구름)를 켜고 싶다면
Caddy가 HTTP-01 챌린지를 못 받으므로 둘 중 하나:
1. **Cloudflare Origin 인증서** 발급 → Caddy에 `tls origin.crt origin.key` 로 지정, Cloudflare SSL 모드 Full(strict).
2. Caddy에 **Cloudflare DNS 챌린지** 모듈을 넣은 커스텀 이미지 + API 토큰 사용.

기본 가이드(DNS only)면 위 과정 없이 그냥 동작한다.
