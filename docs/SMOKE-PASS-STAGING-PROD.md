# Release Smoke Report

- Generated at: `2026-03-18`
- Requested mode: `http`
- Effective mode: `http`
- Base URL: `https://rpg.c76.org`
- World slug: `chroniken-der-asche`

## Checks
- `GET /up` -> `200`
- `GET /` -> `200`
- `GET /welten` -> `200`
- `GET /w/chroniken-der-asche` -> `200`
- `GET /w/chroniken-der-asche/wissen` -> `200`
- `GET /w/chroniken-der-asche/wissen/enzyklopaedie` -> `200`
- `GET /login` -> `200`
- `GET /wissen` -> `200`
- `GET /wissen/enzyklopaedie` -> `200`
- `GET /hilfe` -> `302` (`https://rpg.c76.org/wissen`)
- `HEAD /` has `X-Request-Id`
- `HEAD /` has `X-Robots-Tag` containing `noindex`
