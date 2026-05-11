-- Migration 053: Seed missing Impressum legal document
-- Impressum was missing from the initial legal_documents seed,
-- causing HTTP 404 on therapano.de/impressum (required for Google OAuth review).

INSERT IGNORE INTO `legal_documents` (`slug`, `title`, `content`, `version`, `is_active`) VALUES
(
    'impressum',
    'Impressum',
    '# Impressum\n\n## Angaben gemäß § 5 TMG\n\nTheraPano\nPraxis-Software für Tierphysiotherapie und Hundeschulen\n\nVerantwortlich für den Inhalt:\nFlorian Wenzel\n\n## Kontakt\n\nE-Mail: info@therapano.de\nWebsite: https://therapano.de\n\n## Haftungsausschluss\n\n### Haftung für Inhalte\n\nDie Inhalte unserer Seiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen.\n\n### Haftung für Links\n\nUnser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.\n\n## Urheberrecht\n\nDie durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.',
    '1.0',
    1
);
