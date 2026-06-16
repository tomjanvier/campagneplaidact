# Architecture de la factory de campagne

## Principe

Le dépôt est structuré pour séparer clairement :

1. **Le plugin (métier / données)**
2. **Le thème (présentation / expérience one-page)**

Cette séparation facilite la maintenance multisite, la réutilisation et l'évolution indépendante des couches.

## Arborescence cible

```text
wp-content/
├─ plugins/
│  └─ plaidact-campaign-core/
│     ├─ plaidact-campaign-core.php
│     └─ includes/
│        ├─ class-plaidact-campaign-cpt.php
│        ├─ class-plaidact-campaign-polylang.php
│        ├─ class-plaidact-campaign-petition-workflows.php
│        ├─ class-plaidact-campaign-petitioner-integration.php
│        └─ class-plaidact-campaign-shortcodes.php
│  └─ petitioner/
│     ├─ petitioner.php
│     ├─ dist/
│     └─ dist-gutenberg/
└─ themes/
   └─ plaidact-campaign/
      ├─ style.css
      ├─ functions.php
      ├─ front-page.php
      ├─ header.php
      ├─ footer.php
      ├─ assets/
      │  └─ fonts/
      │     └─ gotham-noir.woff2 (optionnel)
      ├─ inc/
      │  └─ customizer.php
      └─ template-parts/
         └─ sections/
            ├─ partners.php
            ├─ petition.php
            ├─ breves.php
            ├─ articles.php
            └─ social-wall.php
```

## Responsabilités détaillées

### Plugin `plaidact-campaign-core`

- Déclare les contenus de campagne (`plaid_breve`, `plaid_partner`).
- Déclare les taxonomies de classification.
- Gère les métadonnées partenaires (URL externe).
- Expose des shortcodes transverses (pétition / social wall) pour découpler les providers externes du thème.
- Embarque et initialise le moteur `Petitioner` depuis le même plugin.
- Fournit la couche de compatibilité Polylang pour les formulaires pétition / newsletter et pour le mapping vers `Petitioner`.
- Centralise les effets métier partagés de la pétition (notifications, email décideur, redirections frontend).
- Réinjecte les effets métier campagne dans les signatures `Petitioner` : notification, email décideur, newsletter Brevo.

### Module `petitioner`

- Fournit le moteur avancé de pétition embarqué depuis le plugin de référence.
- N’est plus pensé comme un plugin WordPress autonome à activer séparément.
- Est invoqué par `[petition_form]` via le réglage `petition_form_id` du core, ou automatiquement s’il n’existe qu’un seul formulaire publié.

### Thème `plaidact-campaign`

- Gère la structure one-page et les sections visuelles.
- Lit les données du plugin (CPT + meta + shortcodes).
- Expose les options de personnalisation par sous-site via le Customizer.

## Notes multisite

- Plugin à activer en **network activation**.
- Thème partagé et activable sous-site par sous-site.
- Chaque sous-site conserve ses propres réglages Customizer sans divergence de code.
- Si Polylang est actif, les chaînes des formulaires et certains textes de thème deviennent traduisibles sans dupliquer le code.
