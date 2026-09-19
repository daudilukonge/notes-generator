# Notes Generator

A Laravel-based system for creating professional educational notes and presentation-style PDF documents from structured JSON data.

The project is designed to replace the repetitive process of manually creating and formatting every slide in PowerPoint. Instead of designing each slide individually, the system uses reusable HTML/CSS slide layouts and structured JSON content.

The goal is to separate **content from presentation**:

```text
JSON Content
     ↓
Laravel
     ↓
Blade Templates
     ↓
HTML + CSS
     ↓
Slide Renderer
     ↓
PDF
```

This approach allows large courses, modules, lessons, and educational materials to be generated consistently while making future content updates much easier.

---

## 1. Project Purpose

Creating educational course slides manually in PowerPoint can become extremely time-consuming, especially when a course contains many modules and each module contains multiple slides.

This project aims to solve that problem by creating a reusable document-generation system.

Instead of manually creating:

```text
Slide 1
Slide 2
Slide 3
Slide 4
...
Slide 50
```

the content will be represented as structured data:

```json
{
    "title": "PH",
    "type": "content",
    "content": [
        "PH is a measurement of acidity and alkalinity.",
        "The PH scale ranges from 0 to 14."
    ]
}
```

The application will determine how that information should be displayed using predefined slide templates.

This means that changing the content does not require redesigning the slide.

---

# 2. Main Objectives

The system should eventually be able to:

* Create presentation-style educational slides using HTML and CSS.
* Store slide content in JSON.
* Render JSON data through Laravel and Blade.
* Support multiple reusable slide layouts.
* Support images within slides.
* Automatically generate multiple slides from one JSON document.
* Maintain consistent typography, spacing, colors, and branding.
* Generate high-quality PDF documents.
* Allow the same content to be reused for different modules or courses.
* Make content updates possible without modifying the slide design.
* Reduce repetitive manual PowerPoint work.
* Provide a foundation that can later support other document types.

---

# 3. Current Project

This project is currently being developed from a fresh Laravel installation.

Current Laravel version:

```text
Laravel Framework 13.32.0
```

Laravel Boost has also been installed and configured for development assistance.

The project is currently in the initial development stage.

The first milestone is to create a single HTML/CSS slide before introducing JSON-driven rendering and PDF generation.

---

# 4. Technology Stack

## Backend

* Laravel 13
* PHP
* Blade Templates

## Frontend

* HTML5
* CSS3
* Vanilla JavaScript

The project should avoid introducing a frontend framework unless there is a clear technical reason to do so.

The initial implementation should remain simple and dependency-light.

## Data

* JSON

JSON will initially be used as the primary content source for slides.

## Document Generation

A suitable HTML-to-PDF solution will be selected during development.

The PDF engine should be chosen based on its ability to accurately render:

* CSS layouts
* 16:9 slides
* Images
* Typography
* Backgrounds
* Spacing
* Page breaks
* Positioning
* Modern CSS features where practical

Do not install a PDF package until the requirements of the slide renderer have been tested and understood.

---

# 5. Core Concept

The most important architectural principle of this project is:

> **Content and presentation must remain separate.**

The JSON should describe **what the slide contains**.

The Blade templates and CSS should describe **how the slide looks**.

For example:

```json
{
    "type": "content",
    "title": "PH",
    "content": [
        "PH measures acidity and alkalinity.",
        "The scale ranges from 0 to 14."
    ]
}
```

The JSON should not contain HTML such as:

```html
<h1>PH</h1>
```

and should not contain large amounts of CSS.

Instead, the renderer decides how a `content` slide should be displayed.

---

# 6. Planned Data Flow

The intended data flow is:

```text
JSON File
    ↓
Laravel
    ↓
JSON Parser
    ↓
Slide Data
    ↓
Blade View
    ↓
Slide Template
    ↓
CSS
    ↓
Rendered HTML
    ↓
PDF Generator
    ↓
Final PDF
```

For a complete course:

```text
Course JSON
    ↓
Module
    ↓
Slides
    ↓
Slide Templates
    ↓
HTML Document
    ↓
PDF
```

---

# 7. Planned Slide System

The application should use reusable slide types rather than creating a unique template for every slide.

Potential slide types include:

### Title Slide

Used for:

* Course titles
* Module introductions
* Major sections

Example:

```text
UUNDAJI WA BIDHAA ZA NGOZI

MODULE THREE

PH TESTING IN SKINCARE
```

---

### Content Slide

Used for normal educational content.

Example:

```text
PH

PH ni kipimo kinachoelezea kiwango
cha acidity au alkalinity katika solution.
```

---

### Bullet Slide

Used when information is naturally represented as a list.

Example:

```text
PH SCALE

• 0–6.9 = Acidic
• 7 = Neutral
• 7.1–14 = Alkaline
```

---

### Image + Text Slide

Used when an image is required alongside educational content.

Example:

```text
MUUNDO WA NGOZI

[IMAGE]

• Epidermis
• Dermis
• Hypodermis
```

---

### Two-Column Slide

Used when content needs to be divided into two related sections.

---

### Comparison Slide

Used to compare two or more concepts.

For example:

```text
ACIDIC                  ALKALINE

0 ───────── 6.9         7.1 ───────── 14
```

---

### Table Slide

Used for:

* Ingredients
* Formulas
* Measurements
* Comparisons
* Classifications

---

### Formula Slide

Used for cosmetic formulation data.

This may eventually support:

* Ingredient names
* Percentages
* Gram weights
* Phase names
* Instructions
* Notes

---

### Important Note Slide

Used to highlight important information or warnings.

---

### Summary Slide

Used at the end of a section or module.

---

# 8. JSON Structure

The exact JSON schema will evolve during development.

A possible structure is:

```json
{
    "course": {
        "title": "Uundaji wa Bidhaa za Ngozi"
    },
    "module": {
        "number": 3,
        "title": "PH Testing in Skincare"
    },
    "slides": [
        {
            "type": "title",
            "title": "PH",
            "subtitle": "PH TESTING IN SKINCARE"
        },
        {
            "type": "content",
            "title": "PH",
            "content": [
                "PH ni kipimo kinachoelezea kiwango cha acidity na alkalinity.",
                "PH hupimwa kwa kutumia PH meter au PH strips."
            ]
        }
    ]
}
```

The structure should remain:

* readable
* predictable
* easy to edit
* easy to validate
* independent from presentation markup

---

# 9. Image Handling

Images should also be controlled through data.

Example:

```json
{
    "type": "image-text",
    "title": "Muundo wa Ngozi",
    "image": "skin-structure.png",
    "image_position": "right",
    "content": [
        "Epidermis",
        "Dermis",
        "Hypodermis"
    ]
}
```

The application should resolve the image path rather than requiring HTML to be written inside the JSON.

Images may eventually be organized by course and module:

```text
storage/
└── app/
    └── notes/
        └── cosmetics/
            ├── module-01/
            │   └── images/
            ├── module-02/
            │   └── images/
            └── module-03/
                └── images/
```

The exact storage strategy will be finalized during implementation.

---

# 10. Slide Dimensions

The primary output should be designed around a presentation-style 16:9 aspect ratio.

The slide should behave as a fixed visual canvas.

Conceptually:

```text
┌────────────────────────────────────────────────────┐
│                                                    │
│                                                    │
│                    SLIDE CONTENT                   │
│                                                    │
│                                                    │
└────────────────────────────────────────────────────┘
```

The HTML/CSS implementation should maintain consistent proportions.

The PDF renderer must preserve those proportions when producing the final document.

---

# 11. Design System

The project should eventually have a centralized visual design system.

This should control:

* Primary colors
* Secondary colors
* Background colors
* Text colors
* Typography
* Heading sizes
* Body text sizes
* Spacing
* Border radius
* Shadows
* Image treatments
* Footer styles
* Page numbering
* Module indicators

The goal is to make the entire document visually consistent.

A design change should ideally require changing the design system rather than editing every slide individually.

---

# 12. Blade Architecture

Blade should be used to separate reusable layouts from individual slide types.

A possible structure is:

```text
resources/views/
└── slides/
    ├── layout.blade.php
    ├── title.blade.php
    ├── content.blade.php
    ├── bullets.blade.php
    ├── image-text.blade.php
    ├── two-column.blade.php
    ├── comparison.blade.php
    ├── table.blade.php
    ├── formula.blade.php
    ├── note.blade.php
    └── summary.blade.php
```

The final structure may change as the application develops.

Do not create templates unnecessarily.

A new template should only be introduced when an existing template cannot reasonably represent the required slide.

---

# 13. CSS Architecture

CSS should be organized around reusable classes and components.

For example:

```text
resources/
└── css/
    ├── slides.css
    ├── variables.css
    ├── typography.css
    └── components.css
```

The exact structure may be simplified if the project does not require multiple files.

CSS should avoid excessive duplication.

Instead of:

```css
.slide-one-title {}
.slide-two-title {}
.slide-three-title {}
```

use reusable classes:

```css
.slide-title {}
```

The system should be designed around reusable components.

---

# 14. JavaScript

Vanilla JavaScript may be used for tasks such as:

* Slide preview
* Dynamic rendering
* Navigation during development
* Validation
* Image handling
* Preview controls
* Optional client-side interactions

JavaScript should not be used when the same functionality can be handled more simply by Laravel or Blade.

The final PDF should not depend on unnecessary client-side behavior.

---

# 15. Development Phases

Development should proceed incrementally.

## Phase 1 — Basic Laravel Setup

* Confirm Laravel installation.
* Configure environment.
* Create basic route.
* Create first Blade view.
* Verify browser rendering.

---

## Phase 2 — First Slide

Create one 16:9 HTML/CSS slide.

The first slide should establish:

* Dimensions
* Typography
* Background
* Spacing
* Basic branding
* Header/footer behavior

No JSON or PDF generation is required yet.

---

## Phase 3 — Slide Templates

Create reusable slide templates.

Initial templates may include:

* Title
* Content
* Bullet
* Image + Text
* Two Column

---

## Phase 4 — JSON Rendering

Introduce JSON as the source of slide content.

The application should:

1. Read JSON.
2. Decode it.
3. Iterate through slides.
4. Determine each slide type.
5. Render the correct Blade template.
6. Produce a complete HTML document.

---

## Phase 5 — Images

Implement image handling.

Test:

* Local images
* Different image sizes
* Portrait images
* Landscape images
* Transparent PNGs
* Image cropping
* Image positioning

---

## Phase 6 — PDF Generation

Select and install the appropriate PDF engine.

Test:

* One slide
* Multiple slides
* Images
* Fonts
* Backgrounds
* Page breaks
* 16:9 dimensions

---

## Phase 7 — Real Course Content

Use actual cosmetic course material.

The first realistic test will be:

```text
Module Three
PH Testing in Skincare
```

This will allow the system to be tested with real educational content instead of artificial placeholder data.

---

## Phase 8 — Formula and Advanced Slides

Introduce specialized layouts for:

* Cosmetic formulas
* Ingredient tables
* Procedures
* Safety notes
* Comparisons
* Ingredient classifications
* Course summaries

---

## Phase 9 — PDF Quality and Optimization

Improve:

* Typography
* Image quality
* PDF file size
* Page breaks
* Rendering consistency
* Performance
* Error handling

---

# 16. Development Principles

The following principles should guide development.

### 1. Build incrementally

Do not build the entire application at once.

Every stage should produce something testable.

---

### 2. Keep content separate from design

JSON should contain content.

Blade should control structure.

CSS should control appearance.

---

### 3. Prefer reusable components

If something is used more than once, consider whether it should become a reusable component.

---

### 4. Avoid unnecessary dependencies

Do not install packages simply because they are popular.

Every dependency should have a clear purpose.

---

### 5. Understand before abstracting

Do not create complex services, repositories, facto
