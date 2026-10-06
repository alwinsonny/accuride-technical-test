# Alwin_ProductBadge

Magento 2 module for the Accuride technical task.

It adds a translatable **Badge** attribute to products, shows the badge next to the product name on the storefront , and adds a `productDetails` GraphQL query that returns product data in the language and currency of the store view sent in the `Store` header.

Tested on:

- Magento Open Source 2.4.8 with sample data
- PHP 8.3
- MySQL 8.0
- OpenSearch 2.19

## Installation

### copy into app/code

```bash
mkdir -p app/code/Alwin/ProductBadge
# copy the contents of this repo into app/code/Alwin/ProductBadge

bin/magento module:enable Alwin_ProductBadge
bin/magento setup:upgrade
bin/magento setup:di:compile`
bin/magento cache:flush
```

## Store setup

The task uses three store views, so this is the setup I tested with. **Create the store views before running `setup:upgrade`**, because the data patch looks them up by code to add the French and German labels.

All three store views sit under Main Website / Main Website Store:

| Store view | Code | Locale | Display currency |
|---|---|---|---|
| English (default) | `en` | en_GB | GBP |
| French | `fr` | fr_FR | EUR |
| German | `de` | de_DE | EUR |

Locale and currency:

```bash
bin/magento config:set --scope=stores --scope-code=fr general/locale/code fr_FR
bin/magento config:set --scope=stores --scope-code=de general/locale/code de_DE

bin/magento config:set currency/options/base GBP
bin/magento config:set currency/options/default GBP
bin/magento config:set currency/options/allow GBP,EUR
bin/magento config:set --scope=stores --scope-code=fr currency/options/default EUR
bin/magento config:set --scope=stores --scope-code=de currency/options/default EUR
bin/magento cache:flush
```

Then add a GBP to EUR rate in **Stores > Currency Rates** (I used 1.17).



## Using it

1. Open a product in the admin, pick a value in the **Badge** dropdown (New, Best Seller or Sale) and save.
2. On the storefront the product name shows the badge in the current store view's language, e.g. `[New]`, `[Nouveau]`, `[Neu]`.

## GraphQL

Send a POST request to `/graphql` with the store view code in the `Store` header.

```
POST http://accuride.local/graphql  (http://accuride.local is the sample local url which i used for this task)
Content-Type: application/json
Store: fr
```

```graphql
query {
  productDetails(sku: "24-MB01") {
    sku
    name
    price
    currency
    stock_status
    badge
  }
}
```

Response for `Store: en`:

```json
{
  "data": {
    "productDetails": {
      "sku": "24-MB01",
      "name": "Sprite Yoga Strap 6 foot [New]",
      "price": 14,
      "currency": "GBP",
      "stock_status": "In stock",
      "badge": "New"
    }
  }
}
```

Response for `Store: fr`:

```json
{
  "data": {
    "productDetails": {
      "sku": "24-MB01",
      "name": "Sprite Yoga Strap 6 foot [Nouveau]",
      "price": 16.38,
      "currency": "EUR",
      "stock_status": "En stock",
      "badge": "Nouveau"
    }
  }
}
```
Response for `Store: de`:

```json
{
    "data": {
        "productDetails": {
            "sku": "24-MB01",
            "name": "Sprite Yoga Strap 6 foot [Neu]",
            "price": 16.38,
            "currency": "EUR",
            "stock_status": "Auf Lager",
            "badge": "Neu"
        }
    }
}
```

An unknown SKU returns a normal GraphQL error instead of failing:

```json
{
  "errors": [
    {
      "message": "Product with SKU \"123\" does not exist.",
      "extensions": { "category": "graphql-no-such-entity" },
      "path": ["productDetails"]
    }
  ],
  "data": { "productDetails": null }
}
```

An empty SKU returns `A product SKU is required.`



## Decisons/Working

**Attribute** (`Setup/Patch/Data/AddBadgeAttribute.php`)
A data patch adds a `badge` dropdown attribute with three options. The French and German labels are saved as store view option labels, so they can be changed in the admin later without a code change. The patch can be reverted.

**Name formatting** (`Model/NameFormatter.php`, `Plugin/Catalog/Model/ProductNamePlugin.php`)
`NameFormatter` gets the badge label for a store view (falling back to the admin label) and appends it to the name. The labels are loaded once per store per request. An `afterGetName` plugin on the product model uses it.

The plugin is registered in `etc/frontend/di.xml` and `etc/graphql/di.xml` only, not globally. This keeps the badge out of the admin, so it never gets saved into the product name or used to build URL keys, and the REST API and exports still return the clean name.

`etc/catalog_attributes.xml` adds `badge` to the `quote_item` attribute group so the mini cart and cart show the formatted name as well.

**GraphQL** (`etc/schema.graphqls`, `Model/Resolver/ProductDetails.php`)
The resolver takes the store from the GraphQL context, which Magento sets from the `Store` header, and loads the product for that store. It uses the same `NameFormatter` as the storefront, so the name and badge always match.
- Currency is read from the store view's configuration, not hardcoded.
- Stock status uses MSI (`IsProductSalableInterface`) against the stock linked to the store's website. The text is translated through `i18n/fr_FR.csv` and `i18n/de_DE.csv`.

A cache identity class tags responses with the product cache tag, so cached GraphQL responses are cleared when the product is saved.

## Assumptions

- The badge value is the same in every store view (global scope). Only the label is translated.
- The English store uses the admin labels, so it doesn't need its own labels.
- Store view codes are `en`, `fr` and `de`. 
- "Wherever it is displayed" means anywhere a customer sees the product name: product page, listings, search, breadcrumbs, mini cart, cart and checkout. It does not include the admin, REST API or exports.
- The core `products` GraphQL query is unchanged. It reads the name directly from product data, and the task asked for a separate query.
- The stock check expects MSI to be enabled, which is the default in Magento 2.4.8.
