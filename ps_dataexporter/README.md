# PS Data Exporter

Module PrestaShop 1.7.6.5+ pour l'export complet des données clients et commandes.

## Fonctionnalités

### Types d'export
- **Clients uniquement** : export des données clients et adresses
- **Commandes uniquement** : export des commandes et détails
- **Complet** : clients + commandes avec toutes les relations

### Niveaux de détail
- **Essentiel** : Clients + Commandes de base
- **Complet** : + Paiements, Transport, États, Factures, Coupons
- **Ultra** : + Paniers, Connexions, Messages, Retours

### Filtres avancés
- Période (date de création)
- Montants (min/max commande)
- Statuts de commande
- Moyens de paiement
- Transporteurs
- Pays
- Départements et régions (France)
- Groupes clients
- Newsletter (abonnés/non-abonnés)
- Avec/sans coupon
- Produits spécifiques
- Catégories

### Sécurité
- Protection contre l'injection CSV
- Téléchargements sécurisés avec token et expiration
- Aucune PII dans les logs
- Validation CSRF

### Performance
- Export par lots (batch)
- Reprise après interruption
- Support gros volumes (10k+ clients, 100k+ commandes)
- Pagination par curseur (pas d'OFFSET)

## Installation

1. Copier le dossier `ps_dataexporter` dans `/modules/`
2. Installer via le back-office PrestaShop
3. Configurer les paramètres dans l'onglet Configuration

## Configuration

| Paramètre | Description | Défaut |
|-----------|-------------|--------|
| Taille des lots | Nombre d'enregistrements par batch | 500 |
| Délimiteur CSV | Séparateur de colonnes | ; |
| Encadrement | Caractère d'encadrement | " |
| BOM UTF-8 | Ajouter BOM pour Excel | Oui |
| Créer ZIP | Archiver les exports | Oui |
| TTL téléchargement | Durée de validité des liens | 24h |

## Structure des fichiers exportés

En mode **relationnel** (1 fichier par entité) :
- `customer.csv`
- `address.csv`
- `orders.csv`
- `order_detail.csv`
- `order_payment.csv`
- etc.

Chaque fichier contient toutes les colonnes de la table correspondante.

## Mapping géographique France

Le module inclut le mapping complet des 101 départements français vers leurs régions, permettant le filtrage par :
- Code département (01, 02, ..., 976)
- Nom de région (Île-de-France, Occitanie, etc.)

## Compatibilité

- PrestaShop : 1.7.6.5 - 1.7.99.99
- PHP : 7.2+
- MySQL : 5.7+ / MariaDB 10.2+

## Licence

Commercial - Tous droits réservés

## Auteur

Claude Code - 2025
