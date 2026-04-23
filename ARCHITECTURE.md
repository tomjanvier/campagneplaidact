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
│        └─ class-plaidact-campaign-shortcodes.php
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

### Thème `plaidact-campaign`

- Gère la structure one-page et les sections visuelles.
- Lit les données du plugin (CPT + meta + shortcodes).
- Expose les options de personnalisation par sous-site via le Customizer.

## Notes multisite

- Plugin à activer en **network activation**.
- Thème partagé et activable sous-site par sous-site.
- Chaque sous-site conserve ses propres réglages Customizer sans divergence de code.
