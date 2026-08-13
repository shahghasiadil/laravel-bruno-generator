# Bruno v4 Roadmap for `laravel-bruno-generator`

Status: proposal · Last updated: 2026-08-13 · Target: v2.0 → v3.0

Bruno 4.0.0 shipped with typed variables, per-field descriptions, a reworked
secrets model, Bruno Apps, and a stated transition away from `.bru` toward the
OpenCollection YAML format. This document audits what this package emits today
against the current Bruno/OpenCollection schemas, then lays out the work in
phases.

Reference material used for this audit:

- [Bruno v4.0.0 release notes](https://github.com/usebruno/bruno/releases/tag/v4.0.0)
- [Bruno v4 migration guide / breaking changes](https://github.com/usebruno/bruno/discussions/8257)
- [OpenCollection YAML structure reference](https://docs.usebruno.com/opencollection-yaml/structure-reference)
- [OpenCollection YAML samples](https://docs.usebruno.com/opencollection-yaml/samples)
- [Bruno variables overview (typed variables)](https://docs.usebruno.com/variables/overview)
- [Environment variables (YAML/BRU env schema)](https://docs.usebruno.com/variables/environment-variables)
- [Bru tag reference](https://docs.usebruno.com/bru-lang/tag-reference)
- [Bruno Apps](https://docs.usebruno.com/apps/overview)

---

## 1. Audit: what we emit vs. what Bruno expects

### 1.1 The YAML output is not OpenCollection

`YamlFormatSerializer` was written against an assumed schema. Nearly every
section differs from the published spec, so YAML collections generated today
load only partially in Bruno.

| Section | We emit | OpenCollection expects |
|---|---|---|
| File extension | `.yaml` | `.yml` |
| Collection root | `bruno.json` | `opencollection.yml` |
| `info` | `name`, `description`, `seq` | `name`, `type`, `seq`, `tags` (no `description`) |
| `http.method` | uppercase (correct) | uppercase |
| `http.headers` | map `{name: value}` | array of `{name, value, disabled?, description?}` |
| `http.params` | `{query: {k: v}}` | flat array of `{name, value, type: query\|path, disabled?, description?}` |
| `http.body` | `{mode, json: <map>}` | `{type, data}` — `data` is a **string** for json/text/xml/graphql, an array of `{name,value}` for form types |
| `http.auth` | `{mode, <mode>: {...}}` | `{type, ...flat credential fields}` or the scalar `inherit` |
| scripts | `runtime.script.beforeRequest` | `runtime.scripts: [{type: before-request\|after-response\|tests, code}]` |
| tests | `runtime.tests: <string>` | a `tests`-typed entry in `runtime.scripts` |
| assertions | not emitted | `runtime.assertions: [{expression, operator, value}]` |
| `settings` | correct shape | `encodeUrl`, `timeout`, `followRedirects`, `maxRedirects` |
| `docs` | correct | markdown block scalar |
| environments | `{name, variables: {k: v}}` | `{name, variables: [{name, value, enabled, secret, description?}]}` |

Consequence: headers, query params, bodies, auth, scripts and tests are all
silently dropped or mangled on YAML import. This is the single highest-value
fix in the roadmap.

### 1.2 `settings` is dropped for every request

`CollectionOrganizerService::reassignSequences()` rebuilds each `BrunoRequest`
with named arguments but omits `settings`, so it defaults to `null`
(`src/Services/CollectionOrganizerService.php:106`). Every request loses the
`settings` block the normalizer just built — the documented
`advanced.request_settings` config has no effect on real output.

### 1.3 Path parameters are never serialized

`RouteNormalizerService::extractPathVariables()` builds sensible example values
(`src/Services/RouteNormalizerService.php:214`), `BrunoRequest::$pathVariables`
carries them, and no serializer ever writes them. Meanwhile `buildUrl()`
rewrites `/users/{id}` to `/users/{{id}}`, which Bruno treats as an undefined
runtime variable rather than a path parameter. Bruno's own convention is
`/users/:id` plus a `params:path` block (`.bru`) or `type: path` entries
(YAML), which gives users an editable value in the UI.

### 1.4 Query parameters are never generated

`extractQueryParams()` returns `[]` unconditionally with a "will be enhanced"
comment (`src/Services/RouteNormalizerService.php:204`), yet
`request_generation.generate_query_params` is documented in config and README.

### 1.5 No folder- or collection-level files

We emit only requests, environments and `bruno.json`. Bruno supports:

- `folder.bru` / `folder.yml` — folder name, `seq`, docs, folder-level headers,
  auth, vars, and scripts.
- `collection.bru` / `opencollection.yml` — collection-level auth, headers,
  vars, scripts and docs.

Without a collection-level auth block there is nothing for requests to
`inherit`, so we repeat an identical bearer block in every single file.

### 1.6 Auth is applied indiscriminately

`determineAuth()` computes `$hasAuth` from auth middleware and then ignores it:
when `auth.mode !== 'none'` every request gets an auth block, public routes
included (`src/Services/RouteNormalizerService.php:266`). Also, our
`AuthType::AWS_SIG_V4 = 'aws-sig-v4'` does not match Bruno's `awsv4`, and we are
missing `apikey`, `oauth1`, `ntlm`, `wsse`, `inherit` and Akamai EdgeGrid.

### 1.7 Tags are computed but never written

`BrunoRequest::$tags` feeds the `tag` grouping strategy and is then discarded.
Bruno uses `meta.tags` / `info.tags` to filter requests during collection runs
(`bru run --tags smoke`), which is exactly what a generated collection wants.

---

## 2. Bruno v4 features worth adopting

| v4 feature | Relevance | Plan |
|---|---|---|
| Typed variables (`string`/`number`/`boolean`/`object`) | High | Emit `@number`/`@boolean`/`@object` in `.bru` and `value: {type, data}` in YAML for env vars and inferred body/query values |
| Variable & field descriptions | High | `@description('''...''')` on headers/params/vars in `.bru`; `description:` keys in YAML. Source them from FormRequest rules and PHPDoc |
| Secret variables | High | Mark `authToken`/`password`/`apiKey`-style env vars as `secret: true` (YAML) / `vars:secret [ ]` (BRU) so they are never written to disk in plaintext |
| Env vars now persist by default | High (docs) | v4 writes `bru.setEnvVar()` results to disk. Our generated login scripts must write tokens to **secret** vars, and the README needs an explicit "don't commit tokens" warning |
| External secret managers moved into env files | Medium | Optional `environments.*.external_secrets` config block emitting the `externalSecrets` section (AWS Secrets Manager, Azure Key Vault, HashiCorp Vault) |
| YAML is the default import format in v4 | High | Flip the package default from `bru` to `yaml` in the next major, keeping `.bru` fully supported |
| Declarative assertions | Medium | Generate `runtime.assertions` / `assert {}` from route metadata instead of only JS `tests` |
| Akamai EdgeGrid + apikey/ntlm/wsse/oauth1 auth | Medium | Extend `AuthType` and the auth config schema |
| Auto-generated docs with folder/collection context | Medium | Emit folder and collection docs from controller/namespace PHPDoc |
| Bruno Apps | Low (stretch) | Optionally scaffold a collection App (login → token → smoke run) for the generated collection |
| Multiple WebSocket messages per request | Low | Only relevant if we ever generate Reverb/broadcast requests; out of scope |
| CLI JUnit `classname` now uses collection path | Low (docs) | Note in README for anyone asserting on CI output |

---

## 3. Laravel-side enhancements (independent of Bruno v4)

1. **Query params from FormRequests and `Route::where`.** Infer `index`-style
   filters/pagination params; use route constraint patterns to pick better path
   param examples than the current name-substring heuristic.
2. **Richer rule → example mapping.** `file`/`image` should select
   `multipart-form`; `in:`/`Rule::enum` should use the first allowed value;
   `date`, `uuid`, `url`, `numeric|between`, `regex` deserve real examples.
   Cast typed examples (`integer` → number, `boolean` → bool) now that Bruno
   supports typed values.
3. **PHP attributes for opt-in control.** `#[BrunoIgnore]`, `#[BrunoName]`,
   `#[BrunoTag]`, `#[BrunoDescription]`, `#[BrunoExample]` on controller methods
   and FormRequest classes.
4. **Scribe-style PHPDoc annotations.** Parse `@bodyParam`, `@queryParam`,
   `@response`, `@authenticated` — a large existing ecosystem of annotated
   Laravel controllers we can read for free.
5. **Auth inheritance model.** Collection-level auth block + `auth: inherit` on
   protected routes + `auth: none` on public ones, driven by middleware
   detection (fixes §1.6).
6. **Login/token bootstrap.** Optional generated `Auth/login` request whose
   post-response script stores the token into a secret env var — makes the
   collection runnable out of the box.
7. **Regeneration that preserves hand edits.** Mirror v4's OpenAPI-sync
   behaviour: keep user-modified values (env values, edited bodies, added
   scripts) on regenerate instead of overwriting. Needs a parse step, so it is
   the largest single item here.
8. **`bruno:check` / drift detection.** Regenerate to a temp dir and diff
   against the committed collection; non-zero exit for CI. Cheap to build on
   top of the existing deterministic output, and very useful in pipelines.
9. **`bruno:generate --routes=` and `--tags=`** for partial regeneration of a
   single module.

---

## 4. Phased delivery

### Phase 1 — Correctness (patch/minor, no config changes)

Goal: what we already claim to support actually works.

1. Fix `settings` loss in `reassignSequences()` (§1.2) + regression test.
2. Rewrite `YamlFormatSerializer` against the OpenCollection spec (§1.1):
   `info`/`http`/`runtime`/`settings`/`docs`, array-shaped headers and params,
   `{type, data}` bodies, flat auth, `runtime.scripts[]`.
3. Change the YAML extension to `.yml` and emit `opencollection.yml` for YAML
   collections (keep `bruno.json` for `.bru` collections).
4. Serialize path params: `:param` URLs plus `params:path` / `type: path`
   entries, behind the existing `parameterize_route_params` flag so current
   users can keep `{{param}}`.
5. Implement `extractQueryParams()` or remove the config key.
6. Emit `meta.tags` / `info.tags`.
7. Golden-file tests for both formats, plus a CI job that runs `bru run --dry`
   (Bruno CLI) over a generated fixture collection to catch schema drift.

**Deliverable:** v2.0.0 — YAML output is spec-correct. Breaking for anyone
depending on the old (broken) YAML shape or `.yaml` filenames; document in
`UPGRADE.md`.

### Phase 2 — Structure and auth

1. `folder.bru` / `folder.yml` generation: name, `seq`, docs.
2. `collection.bru` / `opencollection.yml`: collection-level auth, default
   headers, collection vars, docs.
3. Auth rework: `AuthType` gains `inherit`, `apikey`, `oauth1`, `ntlm`, `wsse`,
   `akamai-edgegrid`; rename `aws-sig-v4` → `awsv4`; requests inherit from the
   collection; public routes get `none` (§1.6).
4. Move default headers from every request to the collection block.

**Deliverable:** v2.1.0 — smaller, more idiomatic collections.

### Phase 3 — v4 native features

1. Typed variables end to end (env vars, body values, folder/collection vars).
2. `@description` / `description:` on headers, params, and env vars, sourced
   from validation rules and PHPDoc.
3. Secret variables: config-driven `secret_variables` list, defaulting to
   `authToken`; `vars:secret` in BRU, `secret: true` in YAML.
4. `externalSecrets` support in environment files.
5. Declarative `assertions` alongside JS tests.
6. Switch the default `output_format` to `yaml`.

**Deliverable:** v3.0.0 — "Bruno v4 native". Requires Bruno ≥ 4.0.0; keep a
`bruno_compatibility` config (`v3` | `v4`) so v3 users can suppress `@type`
annotations, which older Bruno cannot parse.

### Phase 4 — Laravel depth

Items 1–6 and 9 from §3 (rules mapping, attributes, Scribe annotations, login
bootstrap, partial regeneration).

**Deliverable:** v3.1.0.

### Phase 5 — Workflow

1. Non-destructive regeneration (§3.7).
2. `bruno:check` drift command (§3.8).
3. Optional Bruno App scaffold.

**Deliverable:** v3.2.0.

---

## 5. Compatibility matrix to maintain

| Package | Bruno | Formats |
|---|---|---|
| 1.x | 3.x | `.bru`, non-conformant `.yaml` |
| 2.x | 3.x, 4.x | `.bru`, conformant `.yml` |
| 3.x | 4.x (v3 via `bruno_compatibility=v3`) | `.yml` default, `.bru` supported |

Also worth doing alongside: PHP 8.4/8.5 in the CI matrix, and a
`bruno_compatibility` note in the README.

---

## 6. Risks

- **Spec churn.** OpenCollection is still evolving; pin the golden-file tests to
  a documented spec revision and re-verify each Bruno minor.
- **Silent breakage for YAML users.** Anyone consuming today's `.yaml` output
  gets new filenames and a new shape in Phase 1. Ship it as a major with a
  migration section in `UPGRADE.md`.
- **Persisted env vars (v4).** Since `bru.setEnvVar()` now writes to disk,
  generated scripts must target secret vars or we risk teaching users to commit
  tokens.
- **Non-destructive regeneration** needs a `.bru`/YAML *parser*, not just a
  serializer — the largest cost in the roadmap. Consider scoping it to YAML only.
