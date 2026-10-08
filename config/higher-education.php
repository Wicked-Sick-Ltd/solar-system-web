<?php

declare(strict_types=1);

// Authored teaching material, independent of live catalogue counts. Keep field
// references aligned with SolarApi DTOs; no worksheet assumes a complete sample.
return [
    'activities' => [
        'keplers-law' => [
            'title' => 'Kepler’s law: fit, residuals and uncertainty',
            'level' => 'Undergraduate · Year 1',
            'duration' => '60–90 minutes',
            'prerequisites' => 'Logarithms, straight-line fitting and a spreadsheet or Python notebook.',
            'summary' => 'Use the eight planets to test a power law, then distinguish a close numerical fit from an independent experimental test.',
            'question' => 'How convincingly do catalogue orbital elements support a period–size relationship?',
            'start_route' => 'planets.index',
            'start_label' => 'Open the planet catalogue',
            'outcomes' => ['Fit and interpret a power law using dimensionless ratios.', 'Diagnose residuals and shared assumptions in derived data.', 'State what uncertainty information is missing before drawing a conclusion.'],
            'data' => 'Open each of the eight planet pages. Record semi-major axis a in AU and orbital period P in days, the object identifier, source, epoch and retrieval date where supplied. API users can inspect orbital.semi_major_axis_au and orbital.orbital_period_days in object detail responses. Record missing metadata as missing.',
            'steps' => [
                'Build an eight-row table. Keep the original values and units, and document any exclusions. Use P_year = P_days / 365.25 as an explicit Julian-year conversion.',
                'Plot x = log10(a / 1 AU) against y = log10(P / 1 year). Fit y = m x + b with x on the horizontal axis. Report the slope, intercept and residuals y − (m x + b).',
                'Compare m with 1.5 and calculate q = (P / 1 year)^2 / (a / 1 AU)^3 for each planet. Identify the largest residual without claiming it is statistically significant.',
                'Check how a and P were obtained. Discuss why quantities derived from a shared dynamical model may agree by construction. A small residual alone is not evidence of independent measurements.',
                'Extension: for positive, independent measurements with small symmetric uncertainties, derive (σq / q)^2 ≈ (2σP / P)^2 + (3σa / a)^2. Explain why covariance or absent uncertainties prevents applying this blindly.',
            ],
            'deliverable' => 'Submit the source table, one labelled log plot, a residual plot, and a 200-word interpretation separating model assumptions, numerical agreement and measurement uncertainty.',
            'record_heading' => 'Record your result',
            'record_prompts' => ['Slope m and intercept b, including units/conventions:', 'Largest residual and a possible explanation:', 'Missing uncertainty or dependence that limits the claim:'],
            'teaching_notes' => [
                'A slope near 1.5 is expected for approximately Keplerian orbits around a common dominant mass. q is approximately constant in these chosen units; avoid requiring exact equality to one.',
                'The two-body relation is P² = 4π²a³ / [G(M + m)]. Planet masses, perturbations, element definitions and epoch conventions matter when interpreting differences. Do not turn this catalogue exercise into an unqualified test of gravity.',
                'A fit that treats x as exact is a teaching simplification. Without suitable errors and covariance, reward transparent limitations rather than a numerical confidence claim. Missing uncertainties must not become zero error bars.',
            ],
            'assessment' => ['Correct units, transformations and labelled plots.', 'A reproducible source table and stated fitting convention.', 'An explanation of correlated or derived quantities and why residuals alone do not establish significance.'],
            'sources' => [
                ['title' => 'JPL: approximate planetary positions and orbital elements', 'url' => 'https://ssd.jpl.nasa.gov/planets/approx_pos.html'],
                ['title' => 'NASA: Kepler’s laws of planetary motion', 'url' => 'https://science.nasa.gov/resource/orbits-and-keplers-laws/'],
            ],
        ],
        'exoplanet-selection' => [
            'title' => 'Which planets do our surveys find?',
            'level' => 'Undergraduate · Years 1–2',
            'duration' => '90–120 minutes',
            'prerequisites' => 'Summary statistics, logarithmic axes and a spreadsheet or Python notebook.',
            'summary' => 'Compare transit and radial-velocity discoveries while keeping selection effects, missing values and mass conventions visible.',
            'question' => 'Does a difference between discovery samples tell us how common planets really are?',
            'start_route' => 'exoplanets.index',
            'start_label' => 'Explore the exoplanet catalogue',
            'outcomes' => ['Define a repeatable selection before looking at outcomes.', 'Report missingness and limits separately from measured values.', 'Distinguish a discovery sample from an occurrence-rate estimate.'],
            'data' => 'Use the exoplanet directory’s discovery-method filter. Before collecting values, define a rule such as all listed planets whose names begin with a chosen prefix, or a recorded list of identifiers. Apply the same rule to Transit and Radial Velocity. A manually chosen subset is exploratory, not a representative survey.',
            'steps' => [
                'Record the selection rule, date, method and object identifiers. From each detail page collect period in days and radius in Earth radii when available. Record the total selected count and usable count for each variable and method.',
                'Keep unknown values blank. Store upper/lower limits separately and exclude them from an ordinary measured-value median. State this exclusion and count it; excluding missing values can itself bias a result.',
                'For positive measured values, plot period against radius on logarithmic axes, marking discovery method. If there are too few usable radii, compare period distributions instead and explain the change.',
                'Report a median and sample size for each method. Discuss transit alignment, observing duration and measurement sensitivity as explanations for differences. Do not label the groups controlled or random samples.',
                'Extension: compare mass only after checking provenance. A radial-velocity minimum mass M sin(i) is not generally a true mass. Explain what survey completeness, target-star counts and detection efficiencies would be needed to estimate occurrence rates.',
            ],
            'deliverable' => 'Submit a frozen selection table, a missingness/limit count table, one labelled comparison plot and a 250-word argument stating what the sample can and cannot establish.',
            'record_heading' => 'Audit the comparison',
            'record_prompts' => ['Selection rule and selected / usable counts for each method:', 'One missingness pattern and one selection effect:', 'A conclusion justified by this sample, and a claim it cannot support:'],
            'teaching_notes' => [
                'This activity has no fixed count or expected median: catalogues evolve and the selection rule matters. A reproducible small sample is preferable to a large sample with an undocumented stopping rule.',
                'Transit geometry and repeated-event detection affect what can be found; radial velocity measures stellar motion along the line of sight. Encourage explanations grounded in the method, not a claim that catalogue fractions equal population fractions.',
                'For API work, discovery_method is a top-level field. Archive values live under source_data: pl_orbper (days), pl_rade (Earth radii), and pl_bmasse (Earth masses or minimum mass). Check the corresponding lim flags, err1/err2 fields and mass_provenance; absent keys remain missing.',
            ],
            'assessment' => ['A selection another student can reproduce, with counts before and after exclusions.', 'Correct units, treatment of limits and readable plots.', 'A clear distinction between catalogue demographics and occurrence rates.'],
            'sources' => [
                ['title' => 'NASA: how we find and characterize exoplanets', 'url' => 'https://science.nasa.gov/exoplanets/how-we-find-and-characterize/'],
                ['title' => 'NASA Exoplanet Archive: parameter definitions and flags', 'url' => 'https://exoplanetarchive.ipac.caltech.edu/docs/API_PS_columns.html'],
            ],
        ],
        'reproducible-research' => [
            'title' => 'From catalogue value to reproducible claim',
            'level' => 'Undergraduate · Years 2–3',
            'duration' => '60–90 minutes',
            'prerequisites' => 'Reading a scientific paper and basic uncertainty notation. Coding is optional.',
            'summary' => 'Follow one exoplanet measurement to its reference and build a small evidence package that a partner can independently audit.',
            'question' => 'Could another researcher reconstruct your claim after the catalogue changes?',
            'start_route' => 'exoplanets.index',
            'start_label' => 'Choose an exoplanet',
            'outcomes' => ['Trace a measurement through its catalogue and literature reference.', 'Distinguish retrieval time, publication date and observation epoch.', 'Preserve enough evidence to reproduce a narrowly stated scientific claim.'],
            'data' => 'Choose one exoplanet with a reported period and another measurement of interest. Follow its NASA Exoplanet Archive link to the parameter references. The site uses the Planetary Systems Composite Parameters catalogue: values for a planet can come from different papers or calculations.',
            'steps' => [
                'Write a narrow, testable claim using a quantity, unit and scope. Record the planet identifier and name, displayed value, asymmetric uncertainty or limit, mass provenance if relevant, page URL and catalogue retrieval timestamp.',
                'Follow the parameter’s archive reference to the original study. Record title, authors, publication year and DOI or stable URL. Identify whether the value was measured, fitted or calculated, and whether its uncertainty has a stated confidence convention.',
                'Compare a second quantity for the same planet. Check whether it comes from the same study and model assumptions. Explain why combining composite values can produce an inconsistent physical model.',
                'Save the selected source rows or a small permitted extract, keeping field names and units. Add a README with query/filter, access time, source reference, exclusions and every conversion. For code, record package versions and random seeds if used.',
                'Swap your evidence package with a partner. Ask them to reproduce the claim without further explanation. Record discrepancies and revise the package. Distinguish rerunning your calculation from independently validating the science.',
            ],
            'deliverable' => 'Submit the claim, a two-measurement provenance table, the small evidence package and a short peer audit identifying at least one limitation or unresolved assumption.',
            'record_heading' => 'Build the evidence trail',
            'record_prompts' => ['Quantity / value / units / uncertainty or limit / original reference:', 'Retrieval time versus publication date or observation epoch:', 'Peer audit: what could be reproduced, and what still needs evidence?'],
            'teaching_notes' => [
                'The composite catalogue fills parameter gaps using multiple references and may include calculated quantities. It is a starting point for investigation; a row is not guaranteed to be a single self-consistent physical solution.',
                'An archive retrieval timestamp is not an observation date. Students should explicitly mark unavailable epoch, uncertainty or confidence metadata rather than manufacture precision.',
                'Keep the package small and respect source-specific reuse and attribution requirements. Do not include personal account data or credentials. A checksum can help establish that saved bytes have not changed, but does not establish scientific correctness.',
            ],
            'assessment' => ['Traceable references for each quantity, including units and uncertainty conventions.', 'A preserved input and documented procedure that a partner can follow.', 'An honest distinction between reproducibility, source agreement and validity.'],
            'sources' => [
                ['title' => 'NASA Exoplanet Archive: about composite parameters', 'url' => 'https://exoplanetarchive.ipac.caltech.edu/docs/pscp_about.html'],
                ['title' => 'NASA Exoplanet Archive: how composite values are calculated', 'url' => 'https://exoplanetarchive.ipac.caltech.edu/docs/pscp_calc.html'],
            ],
        ],
    ],
];
