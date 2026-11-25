# Clean rewrite under a new slug instead of forking

The existing "ACF: Image Hotspots Field" (Andrew Rockwell, 2017) and its fork "Image Hotspots Field for ACF" (`image-hotspots-field-for-acf`, owned by another author on WordPress.org) do the same job, but we neither own that slug nor want to carry their code or credits. We rewrite the plugin from scratch as **Hotspot Field for ACF** (`hotspot-field-for-acf`), GPLv2-or-later, with a single author; implementers work only from a behaviour spec and never see the old code, so nothing is copied.

## Consequences

- No data compatibility with either existing plugin: the stored value format is ours to define, and sites switching over must re-place their Hotspots.
- Behaviour may match the old plugins (ideas are not copyrightable), but no code, comments or assets may be reused from them.
