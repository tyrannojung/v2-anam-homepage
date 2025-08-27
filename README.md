# Anam145 Homepage

안암145 공식 홈페이지 소스코드입니다.

## 시스템 요구사항

### 필수 요구사항
- **PHP**: 7.4 ~ 8.2
- **Database**: MySQL 5.7+ 또는 MariaDB 10.1+
- **웹서버**: Apache 2.4+ (mod_rewrite 필요) 또는 Nginx
- **메모리**: 최소 128MB

### PHP 필수 확장 모듈
```bash
# 필수
- curl
- gd
- iconv 또는 mbstring
- json
- openssl
- mysqli
- PDO_MySQL
- SimpleXML
- Zend OPcache

# 권장
- apcu (캐시 성능 향상)
- exif (이미지 자동 회전)
- fileinfo (첨부파일 보안 검사)
- zip
```

## 로컬 환경 설정 (macOS)

### 1. 필수 프로그램 설치

#### PHP 설치
```bash
# Homebrew 설치 (없는 경우)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# PHP 8.2 설치
brew install php@8.2
brew link --force --overwrite php@8.2

# PATH 설정
echo 'export PATH="/opt/homebrew/opt/php@8.2/bin:$PATH"' >> ~/.zshrc
echo 'export PATH="/opt/homebrew/opt/php@8.2/sbin:$PATH"' >> ~/.zshrc
source ~/.zshrc

# 설치 확인
php -v
```

#### 데이터베이스 설치
```bash
# MariaDB 설치 (권장)
brew install mariadb
brew services start mariadb

# 또는 MySQL 설치
brew install mysql
brew services start mysql
```

### 2. 프로젝트 설정

#### 데이터베이스 설정
```bash
# 데이터베이스 생성
mysql -e "CREATE DATABASE IF NOT EXISTS anam_homepage CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 사용자 생성 (macOS는 root 접근 제한이 있어 별도 사용자 필요)
mysql -e "CREATE USER IF NOT EXISTS 'anam'@'localhost' IDENTIFIED BY 'your_password';"
mysql -e "GRANT ALL PRIVILEGES ON anam_homepage.* TO 'anam'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
```

#### 설정 파일 복사 및 수정
```bash
# config 파일 복사
cp files/config/config.php.template files/config/config.php

# files/config/config.php 수정
# 다음 항목들을 수정하세요:
# - DB 접속 정보 (10-12번 줄)
#   'user' => 'anam',
#   'pass' => 'your_password',
#   'database' => 'anam_homepage',
#
# - URL 설정 (49번 줄)
#   'default' => 'http://localhost:8000/',
#
# - Rewrite 설정 (54, 161번 줄)
#   'rewrite' => 0,
#   'use_rewrite' => false,
#
# - 암호화 키 생성 (35-37번 줄)
#   각 키에 64자 이상의 랜덤 문자열 입력
```

#### 파일 권한 설정
```bash
# files 폴더 쓰기 권한
chmod -R 777 files/
```

#### Router 파일 생성 (PHP 내장 서버용)
```bash
cat > router.php << 'EOF'
<?php
// PHP Built-in Server Router
$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8000';
$_SERVER['HTTPS'] = '';

if (file_exists(__DIR__ . $_SERVER['REQUEST_URI']) && is_file(__DIR__ . $_SERVER['REQUEST_URI'])) {
    return false;
}

require_once __DIR__ . '/index.php';
EOF
```

### 3. 데이터베이스 초기화

#### 새로운 설치
```bash
# 브라우저에서 접속
http://localhost:8000/

# 설치 마법사를 따라 진행
```

#### 기존 데이터 복원 (백업 파일이 있는 경우)
```bash
# SQL 백업 파일 복원
mysql anam_homepage < backup.sql

# 도메인 정보 업데이트
mysql anam_homepage -e "UPDATE wp_domains SET domain='localhost:8000' WHERE domain IS NOT NULL;"

# 메뉴 URL 업데이트
mysql anam_homepage -e "
UPDATE wp_menu_item SET url=CONCAT('/', url) 
WHERE url NOT LIKE '/%' 
AND url NOT LIKE 'http%' 
AND url != '';
"
```

### 4. 서버 실행

```bash
# 개발 서버 실행 (router.php 필수!)
php -S localhost:8000 router.php

# 브라우저에서 접속
http://localhost:8000
```

## 관리자 접속

- **URL**: http://localhost:8000/index.php?module=admin
- **초기 계정**: 설치 시 생성
- **비밀번호 변경**: 관리자 로그인 후 회원정보 메뉴에서 변경

## 디렉토리 구조

```
.
├── addons/              # 애드온 모듈
├── assets/              # 정적 자원 (이미지, 비디오)
├── classes/             # PHP 클래스
├── common/              # 공통 라이브러리
├── files/               # 사용자 파일 (캐시, 업로드 등)
├── layouts/             # 레이아웃 테마
│   └── anamwallet/      # 메인 테마
├── modules/             # 기능 모듈
├── pages/               # 정적 페이지
└── widgets/             # 위젯 컴포넌트
```

## 페이지 수정

### HTML 페이지
- 위치: `layouts/anamwallet/pages/`
- 주요 페이지: about.html, security.html, contact.html, busan_wallet.html

### 스타일시트
- SCSS: `layouts/anamwallet/page.scss`
- 컴파일 후 자동 적용

### 레이아웃
- 헤더: `layouts/anamwallet/_header.html`
- 푸터: `layouts/anamwallet/layout.html` (하단 부분)
- 메인: `layouts/anamwallet/_main.html`

## 문제 해결

### 캐시 초기화
```bash
rm -rf files/cache/*
```

### 로그인 리다이렉션 문제
- router.php 파일이 있는지 확인
- 서버 실행 시 router.php 포함 여부 확인
- 브라우저 쿠키 삭제

### 데이터베이스 접속 오류
- MariaDB/MySQL 서비스 실행 확인
- 사용자 권한 확인
- config.php의 DB 정보 확인

### 파일 업로드 오류
```bash
chmod -R 777 files/
```

## 라이선스

GNU General Public License v2.0

## 기술 스택

- **CMS**: Rhymix (XpressEngine 기반)
- **언어**: PHP 7.4+
- **데이터베이스**: MariaDB/MySQL
- **프론트엔드**: HTML5, SCSS, JavaScript
- **라이브러리**: jQuery, Swiper, GSAP, AOS

## 문의

- 회사: 안암145
- 이메일: tyrannojung@anam145.com
- 주소: 서울특별시 성북구 안암로 145, 고려대학교 로봇융합관 308호