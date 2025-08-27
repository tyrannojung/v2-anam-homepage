# Anam Wallet 홈페이지 로컬 환경 설정

## 사전 요구사항
- PHP 7.4 ~ 8.2
- MySQL 5.7+ 또는 MariaDB 10.1+
- Composer (선택사항)

## 설치 방법

### 1. 설정 파일 복사
```bash
cp files/config/config.php.template files/config/config.php
```

### 2. config.php 수정
- DB 접속 정보 입력
- 암호화 키 생성 (랜덤 문자열)

### 3. 데이터베이스 설정
```bash
# DB 생성
mysql -e "CREATE DATABASE your_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# DB 사용자 생성
mysql -e "CREATE USER 'your_user'@'localhost' IDENTIFIED BY 'your_password';"
mysql -e "GRANT ALL PRIVILEGES ON your_db_name.* TO 'your_user'@'localhost';"
```

### 4. 로컬 서버 실행 (PHP 내장 서버)
```bash
# router.php 생성 (템플릿은 문서 참조)
php -S localhost:8000 router.php
```

## 주의사항
- `files/` 폴더에 쓰기 권한 필요
- PHP 내장 서버 사용 시 router.php 필수
- 프로덕션 환경에서는 Apache/Nginx 권장

## 관리자 접속
- URL: http://localhost:8000/index.php?module=admin
- 초기 계정은 설치 시 생성

## 문제 해결
- 캐시 삭제: `rm -rf files/cache/*`
- 로그인 리다이렉션 문제: router.php 확인