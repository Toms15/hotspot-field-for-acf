# Coding standards

Read by the **reviewer** of every PR. Mechanical rules live in PHPCS, Plugin Check and the test suites; this file holds the judgement calls those checks cannot make. Vocabulary follows `GLOSSARY.md`.

## Render context

Every public entry point (shortcode, `hotspot_field_render()`, `hotspot_field_render_hotspots()`, the field type's load/format paths) runs in a **render context**: which post or object supplies the data, which object the visibility check guards, and where on the page the output lands. Most blockers so far were context mix-ups.

- **One object, checked and read.** The object whose visibility/password is checked is the same object whose fields are read. Trace the ID from the attribute or argument through ACF's ID resolution (`acf_get_valid_post_id()`, `acf/validate_post_id`) to the read.
- **Every context, explicitly.** For each new or changed entry point, confirm behaviour in: a normal published post; a preview (global post is the parent, ACF reads the latest revision); an open `have_rows()` loop on another post or on `option`; a password-protected or non-public target; a missing, zero or negative ID; the same Hotspot Field rendered twice on one page; inside an ACF block (block previews included).
- **Unique per page.** Generated `id`s and the attributes that reference them (`popovertarget`, `aria-controls`) stay unique when the same Hotspots render more than once.
- **Security fixes keep previews working.** A visibility or capability fix that breaks preview or revision rendering is a regression, not a fix.

## Tests for render context

- **Distinct objects in fixtures.** A context test builds the two sides as different objects: the loop's post differs from the current post, the global post is the revision's parent while ACF reads the revision, the private target differs from the public current post. A test where both sides coincide proves nothing about the context.
- **Shared helpers.** Context setup goes through the helpers in `tests/php/includes/class-hotspot-field-test-case.php` (real preview, foreign rows loop) rather than ad-hoc `$_GET` / global juggling inside a single test.
- **External behaviour only.** Assert through the public PHP API and the browser (roles, accessible names, ARIA state), never internal classes or CSS-only state classes.

## Secrets and the dev environment

- `ACF_PRO_LICENSE` reaches only the CI step that installs ACF Pro, and never appears in output, URLs in error messages, or logs.
- The dev `.htaccess` keeps dotfiles (`.env`, `.git`, …) unreachable over HTTP; changes to the wp-env mount or web root keep that guarantee and its test.

## Clean room

Code, comments and assets are original (ADR 0001). Flag anything that looks lifted from earlier hotspot plugins.
