# Code and Documentation Style Requirements

**Status:** Required project reference  
**Source:** `13_Clean_Code_Documentation.pdf`  
**Usage:** Read this file before reviewing, writing, or modifying any project code or documentation.

## 1. General clean-code rules

- Follow the standard conventions of the language and the existing project.
- Keep formatting, naming, structure, and terminology consistent.
- Apply KISS: choose the simplest design that meets the requirement.
- Reduce unnecessary complexity, duplication, branching, and abstraction.
- Follow the Boy Scout rule: leave every file cleaner than you found it.
- Identify and correct root causes instead of adding workarounds that hide problems.
- Treat rushed prototype code as temporary. Clean it before other developers must maintain or extend it.

## 2. Naming

- Choose descriptive and unambiguous names.
- Make names pronounceable and easy to discuss with teammates.
- Make important names searchable throughout the repository.
- Use meaningfully different names for different concepts.
- Name variables, functions, classes, files, API fields, and database fields according to their purpose.
- Replace unexplained numeric or string literals with named constants when the value has domain meaning or appears repeatedly.
- Avoid abbreviations that obscure meaning.
- Avoid encoded names and type prefixes such as `str_name`, `i_count`, or `m_total`.
- Use short names such as `i` only in a small, conventional scope where their meaning is obvious.

### Examples

Prefer:

```js
const MAX_PHOTOS_PER_LISTING = 4;

function getListingById(listingId) {
  // ...
}
```

Avoid:

```js
const n = 4;

function get(x) {
  // ...
}
```

## 3. Functions and methods

- Keep functions small.
- Give each function one clear responsibility.
- Extract distinct calculation, validation, persistence, formatting, and communication steps into appropriately named functions.
- Prefer fewer parameters.
- Group related parameters into an object when doing so makes the call easier to understand.
- Avoid boolean or flag parameters that change a function's behavior.
- Replace flag-driven behavior with separate functions whose names state the intended operation.
- Keep function names consistent with their effects and return values.
- Avoid hidden side effects.

### Examples

Prefer:

```js
publishListing(listing);
saveListingDraft(listing);
```

Avoid:

```js
saveListing(listing, true);
saveListing(listing, false);
```

## 4. Comments

- Make the code state **what** it does; use comments to explain **why** it does it.
- Explain non-obvious intent, constraints, tradeoffs, or required ordering.
- Warn about significant consequences, destructive behavior, or the absence of an undo path.
- Do not restate an obvious line of code.
- Do not use comments to compensate for unclear names or overly complex functions.
- Remove commented-out code. Version control preserves previous implementations.
- Update or remove a comment whenever the related behavior changes.

### Example

```js
// Keep sold listings visible because sellers use them as transaction history.
const visibleListings = listings.filter(listing => listing.status !== 'removed');
```

## 5. Documentation types

Maintain the documentation appropriate to the change:

- **README and setup guides:** repository purpose, prerequisites, setup, configuration, run commands, tests, and common troubleshooting.
- **Developer and API documentation:** endpoints, parameters, response shapes, validation rules, errors, and internal library usage.
- **Architecture and design documents:** components, data flow, boundaries, interactions, and important architectural decisions.
- **End-user guides:** task-oriented feature instructions and troubleshooting written for non-technical users.

Update affected documentation in the same change as the code.

## 6. Technical-writing rules

- Use active voice.
- Write direct instructions in the imperative mood.
- Keep paragraphs to three or four sentences at most.
- Put commands or code blocks before the explanation when readers need to execute them.
- Use inline code for file names, paths, variables, functions, field names, and commands.
- Use bold text sparingly for UI labels and critical warnings.
- Use numbered lists only when order matters.
- Use bullets for unordered requirements, options, or facts.
- State exact file names, commands, expected results, defaults, and prerequisites.
- Avoid vague phrases such as “update the configuration” when specific fields or values can be named.

### Example

Prefer:

```md
Run `npm run check`.

The command validates JavaScript syntax and executes the static smoke tests.
```

Avoid:

```md
The checks can be run by executing the appropriate npm command shown above.
```

## 7. Project-specific application

Apply these requirements to the Campus Community Marketplace codebase:

- Keep marketplace listings and moving-help posts as clearly separated concepts.
- Use domain names such as `listingId`, `fromLocation`, `toLocation`, `condition`, and `cashOffer` instead of generic names.
- Put repeated limits, statuses, categories, and condition values in named constants or centralized definitions when practical.
- Keep validation, persistence, rendering, and event handling in separate focused functions.
- Document API inputs, outputs, validation failures, and state changes such as marking a listing sold.
- Warn clearly before destructive actions such as removing a listing or help post.
- Keep the `README.md`, API documentation, tests, CI configuration, and project brief synchronized with implemented behavior.

## 8. Review checklist

Before completing a change, confirm that:

- Names communicate intent without requiring explanatory comments.
- Functions remain small and perform one job.
- Function calls do not rely on unclear flags or excessive arguments.
- Repeated meaningful literals use named constants where appropriate.
- Comments explain intent or consequences rather than obvious mechanics.
- Commented-out code is absent.
- The change follows existing conventions and reduces or preserves overall complexity.
- The implementation fixes the underlying problem.
- Relevant README, API, architecture, or user documentation is current.
- Instructions use active voice, short paragraphs, and properly formatted commands and identifiers.
- Tests and CI checks cover the changed behavior.

