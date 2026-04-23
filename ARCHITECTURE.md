# Usine à sites de campagne PLAID·ACT

## Arborescence proposée

```text
wp-content/
├─ plugins/
│  └─ plaidact-campaign-core/
│     ├─ plaidact-campaign-core.php
│     └─ includes/
│        └─ class-plaidact-campaign-cpt.php
└─ themes/
   └─ plaidact-campaign/
      ├─ style.css
      ├─ functions.php
      ├─ front-page.php
      ├─ header.php
      ├─ footer.php
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

## Notes multisite

- Plugin `plaidact-campaign-core` à activer en **network activation**.
- Le thème est partagé par tous les sous-sites de campagne.
- Les réglages du Customizer restent isolés par sous-site, ce qui permet de personnaliser chaque campagne sans dupliquer le code.
