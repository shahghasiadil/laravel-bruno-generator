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

### Phase 1 — Correctness (patch/minor, no config changes) — DONE

Goal: what we already claim to support actually works.

1. ✅ Fix `settings` loss in `reassignSequences()` (§1.2) + regression test.
2. ✅ Rewrite `YamlFormatSerializer` against the OpenCollection spec (§1.1):
   `info`/`http`/`runtime`/`settings`/`docs`, array-shaped headers and params,
   `{type, data}` bodies, flat auth, `runtime.scripts[]`.
3. ✅ Changed the YAML extension to `.yml`. **Deferred:** emitting
   `opencollection.yml` as the collection root — kept `bruno.json` for both
   formats for now, since the collection-root YAML schema (as opposed to the
   per-request schema) isn't fully documented in Bruno's public docs and
   guessing it risks repeating the exact mistake this phase fixed. Revisit
   once verified against a live Bruno v4 install.
4. ✅ Serialize path params: `:param` URLs plus a `params:path` block, behind
   a new `request_generation.path_param_style` config key (`colon` default,
   `double_brace` to keep the old `{{param}}` behavior).
5. ✅ Implemented `extractQueryParams()` — infers flat query params from
   FormRequest rules on GET/HEAD routes.
6. ✅ Emit `meta.tags` / `info.tags`.
7. **Deferred:** golden-file/CI-level verification against the real Bruno CLI
   (`bru run --dry`) — out of scope for this pass; unit/feature tests added
   instead (see `tests/Unit/YamlFormatSerializerTest.php`).

**Delivered as:** the `settings` fix, an OpenCollection-conformant
`YamlFormatSerializer`, real path/query params, and tag output, all on top
of `main`. Breaking for anyone depending on the old (non-conformant) YAML
shape or `.yaml` filenames; documented in `UPGRADE.md`.

**Verification caveat:** this phase's tests could not be executed in the
authoring sandbox — the sandbox's GitHub-access-scoping proxy makes
`composer install` fail deterministically (Composer's non-interactive
auth-retry path crashes on the repeated 403s from unattached dev
dependencies; see commit history for the full investigation). CI
(`.github/workflows/run-tests.yml`, `.github/workflows/phpstan.yml`) has
real GitHub access and is the actual verification for this phase — check
its result on the pushed branch before merging.

### Phase 2 — Structure and auth — PARTIALLY DONE

1. **Deferred:** `folder.bru` / `folder.yml` generation (name, `seq`, docs).
   Not started — needs a per-folder sequencing/docs source we don't compute
   yet, and (for YAML) the same collection-root schema risk as Phase 1 item 3.
2. ✅ `collection.bru`: collection-level auth block that protected requests
   point at via `auth: inherit`, built from the same `auth.mode` config
   already used for per-request auth. **`.bru` only** — the YAML
   equivalent needs the same unverified `opencollection.yml` schema flagged
   in Phase 1, so YAML requests still inline full auth per request rather
   than using `inherit`. `FormatSerializerInterface::serializeCollectionAuth()`
   is in place so wiring in the YAML side later is additive, not a redesign.
   **Deferred:** collection-level default headers and collection vars (see
   item 4 below for why headers specifically were not moved).
3. ✅ Auth rework: `AuthType` gained `inherit`, `apikey`, `oauth1`, `ntlm`,
   `wsse`, `akamai-edgegrid`; renamed `aws-sig-v4` → `awsv4`; protected
   requests now default to `auth: inherit` (toggle via
   `auth.inherit_from_collection`); public routes correctly get no auth block
   at all, fixing §1.6.
4. **Deferred:** moving default headers to the collection block. On
   inspection, Bruno applies collection/folder-level headers to every child
   request unconditionally, which would regress the existing per-method
   `Content-Type` stripping for GET/DELETE — the current per-request
   generation is actually more correct. Needs a design that separates
   "shared across all requests" headers from "conditional per method" ones
   before this is worth doing.

**Delivered as:** `AuthType` rework, the auth middleware/inherit fix, and
`collection.bru` generation. Same verification caveat as Phase 1 applies —
run/rely on CI, not a local `composer test` in a restricted sandbox.

### Phase 3 — v4 native features — PARTIALLY DONE

1. **Deferred:** typed variables (`@number`/`@boolean`/`@object` in `.bru`,
   `value: {type, data}` in YAML). On closer reading of the docs, this
   schema applies to `runtime.variables` (script-set vars) and
   folder/collection variables — **not** environment variables, which stay
   plain `name`/`value` per the docs' own example. We don't generate
   runtime or folder/collection variables at all yet (folder/collection
   generation is itself deferred from Phase 2), so there's no anchor point
   for typed variables in current output. Revisit once folder/collection
   variable generation exists.
2. ✅ `@description` on environment variables, `.bru`
   (`@description('''...''')`) and YAML (`description:`), via the new array
   config shape (`'name' => ['value' => ..., 'description' => ...]`).
   **Deferred:** header and query/path param descriptions — would need
   either a config schema for per-header descriptions or FormRequest-rule-
   derived text generation for params; real value, but a separate pass.
   Confirmed `@description` doesn't parse in Bruno 3's `.bru` (absent from
   the v3 docs' tag reference), so it's gated behind the new
   `bruno_compatibility` config (`v3` | `v4`, default `v4`).
3. ✅ Secret variables: `secrets.variable_names` (default `['authToken']`),
   or a per-variable `'secret' => true/false` override via the array config
   shape; `vars:secret [...]` (name-only) in `.bru`, `secret: true` + blanked
   `value` in YAML. Confirmed `vars:secret` predates v4 (present in the v3
   docs too), so no compatibility gating needed here.
4. **Deferred:** `externalSecrets` support in environment files. The exact
   schema wasn't in the pages fetched during the original audit (only a
   release-notes summary mentioning the section exists) — same
   don't-guess-the-schema policy as the deferred YAML collection-root file.
5. **Deferred:** declarative `assertions`. The YAML samples doc uses
   operator names (`eq`, `neq`, `isString`) that don't match the dedicated
   assertions doc's operator names (`equals`, `notEquals`, `isString`), and
   no `.bru` block syntax for assertions was found in the fetched docs —
   the inconsistency itself is a signal not to guess here.
6. ✅ Switched the default `output_format` to `yaml`, matching Bruno's own
   default since v3.1.

**Delivered as:** secret variables, environment variable descriptions, the
`bruno_compatibility` toggle, and the `yaml` default — all on top of Phase
1+2. Same sandbox verification caveat as before: pushed and checked against
real CI rather than a local `composer test`.

### Phase 4 — Laravel depth — PARTIALLY DONE

1. ✅ Route `where()` constraints now feed path parameter examples (numeric,
   alternation, UUID-shaped, and alphabetic/slug constraints recognized;
   falls back to the existing name heuristic otherwise). The FormRequest
   half of item 1 (query params) shipped in Phase 1.
2. ✅ `in:a,b,c` → first value; `between:min,max` → `min`, same as `min:`.
   Fixed a related real bug in the same pass: FormRequest bodies with a
   `file`/`image` rule now actually produce a `multipart-form` body — they
   generated a JSON body with a fake filename string before, and even that
   silently vanished in `.bru` output because the serializer had no
   `multipart-form` case at all. **Deferred:** `Rule::enum(...)` object
   detection, and `regex:`-driven examples — reflecting an arbitrary enum
   class or generating a string that satisfies an arbitrary regex both need
   more design than this pass had room for.
3. **Deferred:** PHP attributes (`#[BrunoIgnore]`, `#[BrunoName]`, etc.).
   Real value, but it's a new subsystem (reflection-based scanning wired
   into route filtering and normalization) rather than a delta on existing
   logic — needs its own pass.
4. **Deferred:** Scribe-style PHPDoc annotations (`@bodyParam`,
   `@queryParam`, etc.).
5. ✅ Already delivered in Phase 2 (collection-level auth + `auth: inherit`).
6. **Deferred:** login/token bootstrap. A heuristic "which route is login"
   guess risks injecting a nonsensical script into an unrelated endpoint;
   doing this safely needs an explicit config mapping (route name → capture
   behavior) rather than auto-detection, which is a small feature in its
   own right.
9. **Deferred:** `--routes=`/`--tags=` partial regeneration.

**Delivered as:** route-constraint-aware path examples, `in:`/`between:`
rule mapping, and the file-upload body type/serialization fix — all on top
of Phases 1–3. Same CI-verification note as before applies.

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
