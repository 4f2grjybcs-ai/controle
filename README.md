# Contrôle Parapente — plugin WordPress

Plugin WordPress pour gérer les **contrôles (révisions) de parapentes** d'un atelier :
demandes en ligne, fiche de contrôle technique, certificat imprimable, suivi client et rappels automatiques.

## Fonctionnalités

- **Formulaire de demande** (`[cp_demande_controle]`) : coordonnées du pilote, équipement (marque, modèle, taille, n° de série, homologation…), prestations (contrôle, recalage, réparation, repliage secours), mode de dépôt. Une référence unique est attribuée (ex. `CP-2026-0001`) et des e-mails sont envoyés à l'atelier et au client. Protections : nonce, champ pot de miel anti-robots, limite de 5 demandes/heure par IP, consentement RGPD.
- **Fiche de contrôle** dans l'admin (menu *Contrôles*) :
  - porosité (porosimètre, en secondes) avec évaluation automatique Conforme / À surveiller / Non conforme ;
  - résistance du tissu (Bettsomètre) et des suspentes (rupture mesurée vs minimum requis) ;
  - calage : cotes constructeur vs mesurées, calcul de l'écart et contrôle de la tolérance ;
  - contrôle visuel (12 points : extrados, intrados, cloisons, suspentes, élévateurs, maillons, accélérateur, freins…) ;
  - réparations, observations pour le client, notes internes ;
  - statut (demande → réceptionnée → en cours → terminée → rendue), verdict (navigable / avec réserves / non navigable), date du prochain contrôle proposée automatiquement.
- **Liste des contrôles** : colonnes pilote, équipement, statut, verdict, échéance (en rouge si dépassée), filtres par statut et par échéance, recherche par référence / pilote / aile / n° de série.
- **Certificat / fiche imprimable** (A4, « enregistrer en PDF » depuis le navigateur). Lien public sécurisé par clé secrète envoyé au client ; les notes internes n'y apparaissent jamais.
- **Suivi client** (`[cp_suivi_controle]`) : le client saisit sa référence + son e-mail pour voir l'avancement, le résultat et accéder à sa fiche.
- **E-mails** : nouvelle demande (atelier + client), changement de statut (optionnel, case à cocher), **rappel automatique** X jours avant l'échéance du prochain contrôle (un seul rappel par échéance, pas de rappel si la même aile a déjà un contrôle plus récent).
- **Réglages** : coordonnées de l'atelier, n° d'agrément, préfixe des références, validité d'un contrôle (24 mois par défaut), délai de rappel, seuils de porosité, tolérance de calage, textes du formulaire et du certificat.

## Installation

1. Copier le dossier `controle-parapente/` dans `wp-content/plugins/` (ou le zipper et l'installer via *Extensions → Ajouter → Téléverser*).
2. Activer **Contrôle Parapente**.
3. Aller dans *Contrôles → Réglages* pour renseigner l'atelier et les seuils.
4. Créer une page « Demande de contrôle » contenant `[cp_demande_controle]` et une page « Suivi de mon contrôle » contenant `[cp_suivi_controle]`.

Pour créer le zip : `cd controle && zip -r controle-parapente.zip controle-parapente`

## Personnalisation

- Le certificat peut être surchargé en copiant `templates/certificate.php` dans `votre-theme/controle-parapente/certificate.php`.
- Filtre `cp_email` pour modifier les e-mails avant envoi.
- À la désinstallation, les réglages sont supprimés mais les fiches de contrôle sont conservées (historique). Définir `define( 'CP_DELETE_DATA', true );` dans `wp-config.php` pour tout supprimer.

## Pré-requis

WordPress 6.0+, PHP 7.4+. Les e-mails passent par `wp_mail()` : un plugin SMTP est recommandé pour une bonne délivrabilité.
