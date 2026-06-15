# 배포 가이드 (EC2 + Docker Compose, 공용 프록시 뒤)

`ootd.viram.dev` 에 올리는 절차.

이 EC2엔 이미 **`eta-caddy` 가 80/443 을 점유한 공용 리버스 프록시**로 돌고 있다.
그래서 우리 스택은 호스트 포트를 열지 않고 eta-caddy 와 같은 네트워크에 붙어,
eta-caddy 가 TLS 를 종료하고 `ootd.viram.dev` 를 `http://ootd-web:80` 으로 넘긴다.

```
eta-caddy (443, TLS)  →  ootd-web (내부 :80, Caddy)  →  ootd-app (php-fpm)
```

DB는 SQLite(파일 하나), 이미지/DB는 호스트 바인드마운트라 백업이 쉽다.

---

## 0. 사전 준비

### Cloudflare DNS
- `ootd` A 레코드 → **EC2 퍼블릭 IP** (이미 다른 도메인이 이 IP를 가리키고 있으면 같은 IP)
- TLS는 eta-caddy(Let's Encrypt)가 처리하므로, 다른 도메인들과 동일한 구름 설정을 따른다.
- 보안 그룹은 이미 80/443 이 열려 있을 것(eta-caddy 가 쓰는 중). 추가 작업 불필요.

---

## 1. 최초 배포

```bash
git clone <레포 URL> ootd && cd ootd

cp .env.production.example .env
nano .env
#  - GEMINI_API_KEY 입력
#  - PROXY_NETWORK 를 eta-caddy 의 네트워크 이름으로 설정 (아래 2번에서 확인)
#  - APP_UID/APP_GID 는 deploy.sh 가 자동 감지하므로 손댈 필요 없음
```

### 2. 공용 프록시 연동

**(a) eta-caddy 의 네트워크 이름 확인 → `.env` 의 `PROXY_NETWORK` 에 기입**
```bash
docker inspect eta-caddy-1 --format '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}'
# 예: eta_default  →  .env 에  PROXY_NETWORK=eta_default
```

**(b) 배포** (APP_KEY 는 composer install 후 deploy.sh 가 자동 생성)
```bash
chmod +x deploy.sh
./deploy.sh
```

**(c) eta-caddy 의 Caddyfile 에 사이트 블록 추가**
먼저 eta-caddy 의 Caddyfile 이 호스트 어디에 마운트됐는지 확인:
```bash
docker inspect eta-caddy-1 --format '{{range .Mounts}}{{.Source}} -> {{.Destination}}{{"\n"}}{{end}}'
```
그 Caddyfile 에 추가:
```
ootd.viram.dev {
    reverse_proxy ootd-web:80
}
```
적용(reload):
```bash
docker exec eta-caddy-1 caddy reload --config /etc/caddy/Caddyfile
#  reload 가 안 되면:  docker restart eta-caddy-1
```

→ `https://ootd.viram.dev` 접속 확인.

---

## 3. 이후 업데이트

```bash
./deploy.sh
```
`git pull → build → composer install → (APP_KEY) → migrate → optimize → up` 자동 수행.
eta-caddy 쪽 설정은 한 번만 하면 되고, 이후엔 건드릴 필요 없다.

---

## 4. 백업

```bash
# DB
cp database/database.sqlite ~/backups/ootd-$(date +%F).sqlite
# 생성된 아바타
tar czf ~/backups/avatars-$(date +%F).tgz storage/app/public/avatars
```

---

## 5. 운영 메모 / 트러블슈팅

- **로그**: `docker compose -f compose.prod.yaml logs -f web` (우리 Caddy), `... logs -f app` (PHP)
- **재시작**: `docker compose -f compose.prod.yaml restart`

### `external network ... not found`
`.env` 의 `PROXY_NETWORK` 이름이 eta-caddy 의 실제 네트워크와 다르다. 위 2-(a) 로 다시 확인.

### 502 Bad Gateway (eta-caddy 로그)
eta-caddy 가 `ootd-web` 을 못 찾는 경우. 두 컨테이너가 같은 네트워크(`PROXY_NETWORK`)에 있는지 확인:
```bash
docker network inspect <PROXY_NETWORK> --format '{{range .Containers}}{{.Name}} {{end}}'
# eta-caddy-1 과 ootd-web-1 이 모두 보여야 함
```

### 권한 오류 (`vendor does not exist and could not be created`)
프로젝트 디렉터리 소유자가 현재 사용자가 아닐 때 (root 로 clone 등):
```bash
sudo chown -R "$(id -u):$(id -g)" .
./deploy.sh
```

### 아바타 생성이 동기 처리
요청 한 건이 Gemini 응답(10~20초)을 잡고 있는다. 1인용이라 지금은 문제없고
타임아웃(php 120s / Caddy 180s)도 넉넉하다. 트래픽이 늘면 큐 잡으로 빼는 게 다음 단계.
