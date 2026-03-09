# Release Smoke Report

- Generated at: `2026-03-09`
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
- `GET /wissen` -> `301` (`https://rpg.c76.org/w/chroniken-der-asche/wissen`)
- `GET /wissen/enzyklopaedie` -> `301` (`https://rpg.c76.org/w/chroniken-der-asche/wissen/enzyklopaedie`)
- `GET /hilfe` -> `301` (`https://rpg.c76.org/w/chroniken-der-asche/wissen`)
- `HEAD /` has `X-Request-Id`
- `HEAD /` has `X-Robots-Tag` containing `noindex`
