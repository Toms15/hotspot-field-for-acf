# Hotspot Field for ACF

An ACF field type that lets an editor mark points of interest on an image, and lets visitors open each point to read about it.

## Language

**Hotspot Field**:
The ACF field type this plugin adds; one Hotspot Field holds an ordered list of Hotspots placed on one Linked Image Field.
_Avoid_: Image map, image mapping field

**Hotspot**:
A single point on an image, positioned in percentages of the image's width and height, with a Label and an optional Description.
_Avoid_: Coordinate, marker, pin, point

**Linked Image Field**:
The ACF image field whose image a Hotspot Field's Hotspots are placed on.
_Avoid_: Image field label, source image

**Label**:
The short, required text that names a Hotspot to visitors and assistive technology.
_Avoid_: Title, text

**Description**:
The optional plain text a visitor reads after opening a Hotspot.
_Avoid_: Content, body, tooltip text

**Popover**:
The panel that shows a Hotspot's Label and Description when a visitor opens it.
_Avoid_: Tooltip, modal
