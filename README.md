# OpenCatalogiBundle

## Hoe werkt Open Catalogi

Open catalogi vormt een index over meerdere bronnen, en maakt informatie hieruit inzichtenlijk en doorzoekbaar voor gebruikers. Het focused hierbij in eerste instantie op software componenten aan de hand van repositories maar kan ook API's, datasets of proccesen indexeren.

## Waar leeft metadata?

Open catalogi indexeerd meta data uitverschillende bronnen maar vanuit een data bij de bron princiepe prevereren we het als een (open)source codebade zichzelf beschrijft.

## Compont Publiceren

Het publiceren van een component op opencatalogi.nl gaat met behulp van een publiccode.yaml bestand in de root van je repository. Om je component te publiceren, dien je een publiccode.yaml bestand te maken dat metadata en informatie over je component bevat. Dit bestand helpt het platform om je component te indexeren en gemakkelijk te vinden voor andere gebruikers.

1.  Maak een `publiccode.yaml` bestand in de root van je repository met een teksteditor of een geïntegreerde ontwikkelomgeving (IDE).

2.  Voeg de vereiste metadata toe aan het `publiccode.yaml` bestand. Een voorbeeld van een basisstructuur:

```yaml
publiccodeYmlVersion: "0.2"
 
name: Medusa
url: "https://example.com/italia/medusa.git"
softwareVersion: "dev"    # Optional
releaseDate: "2017-04-15"
platforms:
  - web

categories:
  - financial-reporting

developmentStatus: development

softwareType: "standalone/desktop"

description:
  en:
    localisedName: medusa   # Optional
    shortDescription: >
          A rather short description which
          is probably useless

    longDescription: >
          Very long description of this software, also split
          on multiple rows. You should note what the software
          is and why one should need it. We can potentially
          have many pages of text here.

    features:
       - Just one feature

legal:
  license: AGPL-3.0-or-later

maintenance:
  type: "community"

  contacts:
    - name: Francesco Rossi

localisation:
  localisationReady: true
  availableLanguages:
    - en
```

Pas dit voorbeeld aan op basis van de specificaties van jouw component. Een volledige beschrijving van de publiccode standaard vind je op [yml.publiccode.tools](https://yml.publiccode.tools/schema.core.html#top-level-keys-and-sections)

3.  Voeg eventuele aanvullende metadata toe die relevant kan zijn voor jouw component, zoals documentatie, afhankelijkheden, contactinformatie of onderhoudsinformatie.

4.  Commit en push het `publiccode.yaml` bestand naar je repository. Hou er rekening mee dat het de eerste keer tot 24 uur kan duren voordat OpenCatalogi je component indexeerd

> :note: Open Catalogi scant github elke nacht, als je een component sneller wilt aanmelden of bijwerken kan dat via (opencatalogi.nl)\[https://opencatalogi.nl/documentation/about] gaan en onder "documentatie->over" (hoofd menu)

## Zijn er mininmum eisen aan een publiccode?

Nee, de publiccode.yaml mag zelfs leeg zijn. Puur het plaatsen daarvan in een open toegankenlijke repository spreekt de intentie uit om een open source oplossing aan te bieden en is voldoende omt e worden mee genomen in de indexatie. In het geval bepaalde gegevens missen worden deze aangevuld vanuit de repository (naam, beschrijving, organisatie, url, licentie).

## Welke velden kan ik verwachten in een publiccode?

In een publiccode.yaml bestand zijn er verschillende properties die gedefinieerd kunnen worden om verschillende aspecten van de software of het project te beschrijven. Deze properties variëren van het geven van basisinformatie zoals de naam van de software, tot meer specifieke informatie zoals de gebruikte licentie of de ontwikkelstatus van de software. De volgende tabel geeft een overzicht van de mogelijke properties, of ze verplicht zijn of niet, wat het verwachte type input is en een korte beschrijving van elk.

Hier is een voorbeeld van hoe de tabel eruit kan zien, gebaseerd op de standaard die wordt beschreven op yml.publiccode.tools:

| Property             | Verplicht | Verwachte Input    | Default                                                            | Enum | Beschrijving                                                 |
|----------------------|-----------|--------------------|--------------------------------------------------------------------|------|--------------------------------------------------------------|
| publiccodeYmlVersion | Nee       | String<SEMVER>     | 0.2                                                                | Nee  | De versie van de publiccode.yml standaard.                   |
| name                 | Nee       | String             | De naam ven de repository waarin de public code is gevonden        | Nee  | De naam van de software.                                     |
| url                  | Nee       | String<URL>        | De url van de repository waarin de public code is gevonden         | Nee  | De URL naar de repository van de software.                   |
| landingURL           | Nee       | String<URL>        | De url onder repository settings (indien opgegeven)                | Nee  | URL naar een landingspagina voor de software.                |
| isBasedOn            | Nee       | String<URL>        | N.v.t.                                                             | Nee  | URL van het originele project, als de software een variant of fork is. |
| softwareVersion      | Nee       | String<SEMVER>     | N.v.t.                                                             | Nee  | De huidige stabiele versie van de software.                  |
| logo                 | Nee       | URL / Pad          | De afbeedling van de repository (indien opgegeven)                 | Nee  | Pad of URL naar het logo van de software.                    |
| platforms            | Nee       | Lijst              | N.v.t.                                                             | Ja   | De platformen waarop de software kan draaien.                |
| releaseDate          | Nee       | Datum (YYYY-MM-DD) | De creatie datum van de repository (indien opgegeven)              | Nee  | De release datum van de huidige softwareversie.              |
| categories           | Nee       | Lijst              | N.v.t.                                                             | Nee  | De categorieën waartoe de software behoort.                  |
| developmentStatus    | Nee       | String             | N.v.t.                                                             | Ja   | De huidige ontwikkelstatus van de software.                  |
| softwareType         | Nee       | String             | N.v.t.                                                             | Ja   | Het type software (e.g., standalone, library, etc.).         |
| description          | Nee       | Object             | De beschrijving van de repository waarind e publiccode is gevonden | Nee  | Bevat gelokaliseerde namen en beschrijvingen van de software.|
| legal                | Nee       | Object             | De licentie van de repository (indien opgegeven)                   | Nee  | Bevat de licentie onder welke de software is vrijgegeven.    |
| maintenance          | Nee       | Object             | N.v.t.                                                             | Nee  | Bevat onderhoudsinformatie voor de software.                 |
| localisation         | Nee       | Object             | N.v.t.                                                             | Nee  | Bevat informatie over de beschikbare talen van de software.  |
| roadmap              | Nee       | String<URL>        | N.v.t.                                                             | Nee  | A link to a public roadmap of the software.  |
| inputTypes           | Nee       | array<String>      | N.v.t.                                                             | Nee  | A link to a public roadmap of the software.  |
| outputTypes          | Nee       | array<String>         | N.v.t.                                                             | Nee  | A link to a public roadmap of the software.  |

## Zijn er uitbreidingen op de publiccode standaard?

Bij het veld softwareType ondersteunen we extra mogenlijkheden

| Software Type         | Beschrijving                                                                                       |
|-----------------------|---------------------------------------------------------------------------------------------------|
| standalone/mobile     | The software is a standalone, self-contained. The software is a native mobile app.                |
| standalone/iot        | The software is suitable for an IoT context.                                                      |
| standalone/desktop    | The software is typically installed and run in a a desktop operating system environment.          |
| standalone/web        | The software represents a web application usable by means of a browser.                           |
| standalone/backend    | The software is a backend application.                                                            |
| standalone/other      | The software has a different nature from the ones listed above.                                   |
| softwareAddon         | The software is an addon, such as a plugin or a theme, for a more complex software.               |
| library               | The software contains a library or an SDK to make it easier to third party developers.            |
| configurationFiles    | The software does not contain executable script but a set of configuration files.                 |
| api                   | The repository/folder doesn't contain software but an OAS api description.                        |
| schema                | The repository/folder doesn't contain software but a schema.json object description.              |
| data                  | The repository/folder doesn't contain software but a public data file (e.g. csv, xml etc).        |
| process               | The repository/folder doesn't contain software but an executable process (e.g. bpmn2, camunda).   |
| model                 | The repository/folder doesn't contain software but a model (e.g. uml).                            |

Bij het veld platforms ondersteunen we extra mogenlijkheden "haven","kubernetes","azure","aws"

Daarnaast zijn in de normale versie van de standaard de velden "publiccodeYmlVersion","name","url" verplicht en kent public code vanuit de standaard geen default values (die wij ontrekken aan de repository)

## Welke bronnen indexeerd open catalogi naast Github?

Open Catalogi kijkt mee op:

*   https://developer.overheid.nl
*   https://data.overheid.nl
*   https://componentencatalogus.commonground.nl

## Hoe kan ik specifieke nderlandse verwijzingen opnemen naar bijvoorbeeld de GEMMA software catalogus?

OpenCatalogi maakt hiervoor gebruik van de mogenlijkheid om landsspecifieke uitbreidingen op de publiccode standaard toe te voegen (link hier). Je kan deze terugvinden in het voorbeeld bestand onder nl.

De op dit moment door open catalogie ondersteunde landsspecifieke standaarden zijn:

nl.gamma
nl.commonground.layer
nl.upl
nl.

## Hoe werkt federalisatie?

Iedere installatie (Catalogus) van Open Catalogi heeft een directory waarin alle installaties van Open Catalogi zijn opgenomen. Bij het opkomen van een nieuwe catalogus moet deze connectie maken met ten minimale één bestaande catalogus (bij default is dat opencatalogi.nl) voor het ophalen van de directory.

Vervolgens meld de catalogus zich bij de overige catalogusen aan als nieuwe mogenlijke bron. De catalogusen hanteren onderling zowel periodieke pull requests op hun directories als cloudevent gebaseerd berichten verkeer om elkar op de hoogte te houden van nieuwe catalogi en eventueele endpoints binnen die catalogi.

De bestaande catalogi hebben vervolgens de mogenlijkheid om de niewe catlogus mee te nemen in hun zoekopdrachten.

> :note:
>
> *   Bronnen worden pas gebruikt door een catalogus als de beheerder hiervoor akkoord heeft gegeven
> *   Bronnen kunnen zelf voorwaarde stellen aan het gebruikt (bijvoorbeeld alleen met PKI certificaat, of aan de hand van API sleutel)

## Licentie

Deze bundle is beschickbaar onder [EUPL](https://eupl.eu/1.2/nl/) licentie.
