<?php

$status_codes = array(
    array("status" => "000", "description" => "Successfully purchased application forms"),
    array("status" => "001", "description" => "Failed to send SMS to recipient!"),
    array("status" => "002", "description" => "Failed to generate login information for applicant"),
    array("status" => "003", "description" => "Failed to log purchase information to system"),
    array("status" => "004", "description" => "Received invalid request data")
);

$countries = array(
    array("name" => "Afghanistan",  "code" => "+93"),
    array("name" => "Aland Islands",  "code" => "+358"),
    array("name" => "Albania",  "code" => "+355"),
    array("name" => "Algeria",  "code" => "+213"),
    array("name" => "American Samoa",  "code" => "+1"),
    array("name" => "Andorra",  "code" => "+376"),
    array("name" => "Angola",  "code" => "+244"),
    array("name" => "Anguila",  "code" => "+1"),
    array("name" => "Antigua & Barbuda",  "code" => "+1"),
    array("name" => "Argentina",  "code" => "+54"),
    array("name" => "Armenia",  "code" => "+374"),
    array("name" => "Aruba",  "code" => "+297"),
    array("name" => "Ascension Island",  "code" => "+247"),
    array("name" => "Australia",  "code" => "+61"),
    array("name" => "Austria",  "code" => "+43"),
    array("name" => "Azerbaijan",  "code" => "+994"),
    array("name" => "Bahamas",  "code" => "+1"),
    array("name" => "Bahrain",  "code" => "+973"),
    array("name" => "Bangladesh",  "code" => "+880"),
    array("name" => "Barbados",  "code" => "+1"),
    array("name" => "Belarus",  "code" => "+375"),
    array("name" => "Belgium",  "code" => "+32"),
    array("name" => "Belize",  "code" => "+501"),
    array("name" => "Benin",  "code" => "+229"),
    array("name" => "Bermuda",  "code" => "+1"),
    array("name" => "Bhutan",  "code" => "+975"),
    array("name" => "Bolivia",  "code" => "+591"),
    array("name" => "Bosnia & Herzegovina",  "code" => "+387"),
    array("name" => "Botswana",  "code" => "+267"),
    array("name" => "Brazil",  "code" => "+55"),
    array("name" => "British Indian Ocean Territory",  "code" => "+246"),
    array("name" => "British Virgin Islands",  "code" => "+1"),
    array("name" => "Brunei",  "code" => "+673"),
    array("name" => "Bulgaria",  "code" => "+359"),
    array("name" => "Burkina Faso",  "code" => "+226"),
    array("name" => "Burundi",  "code" => "+257"),
    array("name" => "Cambodia",  "code" => "+855"),
    array("name" => "Cameroon",  "code" => "+237"),
    array("name" => "Canada",  "code" => "+1"),
    array("name" => "Cape Verde",  "code" => "+238"),
    array("name" => "Caribean Netherlands",  "code" => "+599"),
    array("name" => "Cayman Islands",  "code" => "+1"),
    array("name" => "Central African Republic",  "code" => "+236"),
    array("name" => "Chad",  "code" => "+235"),
    array("name" => "Chile",  "code" => "+56"),
    array("name" => "China",  "code" => "+86"),
    array("name" => "Christmas Island",  "code" => "+61"),
    array("name" => "Cocos (Keeling) Island",  "code" => "+61"),
    array("name" => "Colombia",  "code" => "+57"),
    array("name" => "Comoros",  "code" => "+269"),
    array("name" => "Congo - Brazzaville",  "code" => "+242"),
    array("name" => "Cook Island",  "code" => "+682"),
    array("name" => "Costa Rica",  "code" => "+506"),
    array("name" => "Côte d'Ivoire",  "code" => "+255"),
    array("name" => "Croatia",  "code" => "+385"),
    array("name" => "Cuba",  "code" => "+53"),
    array("name" => "Curacao",  "code" => "+599"),
    array("name" => "Cyprus",  "code" => "+357"),
    array("name" => "Czech Republic (Czechia)",  "code" => "+420"),
    array("name" => "Denmark",  "code" => "+45"),
    array("name" => "Djibouti",  "code" => "+253"),
    array("name" => "Dominica",  "code" => "+1"),
    array("name" => "Dominican Republic",  "code" => "+1"),
    array("name" => "DR Congo",  "code" => "+243"),
    array("name" => "Ecuador",  "code" => "+593"),
    array("name" => "Egypt",  "code" => "+20"),
    array("name" => "El Salvador",  "code" => "+503"),
    array("name" => "Equatorial Guinea",  "code" => "+240"),
    array("name" => "Eritrea",  "code" => "+291"),
    array("name" => "Estonia",  "code" => "+372"),
    array("name" => "Eswatini",  "code" => "+268"),
    array("name" => "Ethiopia",  "code" => "+251"),
    array("name" => "Falkland Islands (Islas Malvinas)",  "code" => "+500"),
    array("name" => "Faroe Islands",  "code" => "+298"),
    array("name" => "Fiji",  "code" => "+679"),
    array("name" => "Finland",  "code" => "+358"),
    array("name" => "France",  "code" => "+33"),
    array("name" => "French Guiana",  "code" => "+594"),
    array("name" => "French Polynesia",  "code" => "+689"),
    array("name" => "Gabon",  "code" => "+241"),
    array("name" => "Gambia",  "code" => "+220"),
    array("name" => "Georgia",  "code" => "+995"),
    array("name" => "Germany",  "code" => "+49"),
    array("name" => "Ghana",  "code" => "+233"),
    array("name" => "Gibraltar",  "code" => "+350"),
    array("name" => "Greece",  "code" => "+30"),
    array("name" => "Greenland",  "code" => "+299"),
    array("name" => "Grenada",  "code" => "+1"),
    array("name" => "Guadeloupe",  "code" => "+590"),
    array("name" => "Guam",  "code" => "+1"),
    array("name" => "Guatemala",  "code" => "+502"),
    array("name" => "Guernsey",  "code" => "+44"),
    array("name" => "Guinea",  "code" => "+224"),
    array("name" => "Guinea-Bissau",  "code" => "+245"),
    array("name" => "Guyana",  "code" => "+592"),
    array("name" => "Haiti",  "code" => "+509"),
    array("name" => "Honduras",  "code" => "+504"),
    array("name" => "Hong Kong",  "code" => "+852"),
    array("name" => "Hungary",  "code" => "+36"),
    array("name" => "Iceland",  "code" => "+354"),
    array("name" => "India",  "code" => "+91"),
    array("name" => "Indonesia",  "code" => "+62"),
    array("name" => "Iran",  "code" => "+98"),
    array("name" => "Iraq",  "code" => "+964"),
    array("name" => "Ireland",  "code" => "+353"),
    array("name" => "Isle of Man",  "code" => "+44"),
    array("name" => "Israel",  "code" => "+972"),
    array("name" => "Italy",  "code" => "+39"),
    array("name" => "Jamaica",  "code" => "+1"),
    array("name" => "Japan",  "code" => "+81"),
    array("name" => "Jersey",  "code" => "+44"),
    array("name" => "Jordan",  "code" => "+962"),
    array("name" => "Kazakhstan",  "code" => "+7"),
    array("name" => "Kenya",  "code" => "+254"),
    array("name" => "Kiribati",  "code" => "+686"),
    array("name" => "Kosovo",  "code" => "+383"),
    array("name" => "Kuwait",  "code" => "+965"),
    array("name" => "Kyrgyzstan",  "code" => "+996"),
    array("name" => "Laos",  "code" => "+856"),
    array("name" => "Latvia",  "code" => "+371"),
    array("name" => "Lebanon",  "code" => "+961"),
    array("name" => "Lesotho",  "code" => "+266"),
    array("name" => "Liberia",  "code" => "+231"),
    array("name" => "Libya",  "code" => "+218"),
    array("name" => "Liechtenstein",  "code" => "+423"),
    array("name" => "Lithuania",  "code" => "+370"),
    array("name" => "Luxembourg",  "code" => "+352"),
    array("name" => "Macau",  "code" => "+853"),
    array("name" => "Macedonia (FYROM)",  "code" => "+389"),
    array("name" => "Madagascar",  "code" => "+261"),
    array("name" => "Malawi",  "code" => "+265"),
    array("name" => "Malaysia",  "code" => "+60"),
    array("name" => "Maldives",  "code" => "+960"),
    array("name" => "Mali",  "code" => "+223"),
    array("name" => "Malta",  "code" => "+356"),
    array("name" => "Marshall Islands",  "code" => "+692"),
    array("name" => "Martinique",  "code" => "+596"),
    array("name" => "Mauritania",  "code" => "+222"),
    array("name" => "Mauritius",  "code" => "+230"),
    array("name" => "Mexico",  "code" => "+52"),
    array("name" => "Mayotte",  "code" => "+262"),
    array("name" => "Micronesia",  "code" => "+691"),
    array("name" => "Moldova",  "code" => "+373"),
    array("name" => "Monaco",  "code" => "+377"),
    array("name" => "Mongolia",  "code" => "+976"),
    array("name" => "Montenegro",  "code" => "+382"),
    array("name" => "Montserrat",  "code" => "+1"),
    array("name" => "Morocco",  "code" => "+212"),
    array("name" => "Mozambique",  "code" => "+258"),
    array("name" => "Myanmar",  "code" => "+95"),
    array("name" => "Namibia",  "code" => "+264"),
    array("name" => "Nauru",  "code" => "+674"),
    array("name" => "Nepal",  "code" => "+977"),
    array("name" => "Netherlands",  "code" => "+31"),
    array("name" => "New Caledonia",  "code" => "+687"),
    array("name" => "New Zealand",  "code" => "+64"),
    array("name" => "Nicaragua",  "code" => "+505"),
    array("name" => "Niger",  "code" => "+227"),
    array("name" => "Nigeria",  "code" => "+234"),
    array("name" => "Niue",  "code" => "+683"),
    array("name" => "Norfolk Island",  "code" => "+672"),
    array("name" => "North Korea",  "code" => "+850"),
    array("name" => "North Macedonia",  "code" => "+389"),
    array("name" => "North Mariana Islands",  "code" => "+1"),
    array("name" => "Norway",  "code" => "+47"),
    array("name" => "Oman",  "code" => "+968"),
    array("name" => "Pakistan",  "code" => "+92"),
    array("name" => "Palau",  "code" => "+680"),
    array("name" => "Palestine",  "code" => "+970"),
    array("name" => "Panama",  "code" => "+507"),
    array("name" => "Papua New Guinea",  "code" => "+675"),
    array("name" => "Paraguay",  "code" => "+595"),
    array("name" => "Peru",  "code" => "+51"),
    array("name" => "Philippines",  "code" => "+63"),
    array("name" => "Poland",  "code" => "+48"),
    array("name" => "Portugal",  "code" => "+351"),
    array("name" => "Puerto Rico",  "code" => "+1"),
    array("name" => "Qatar",  "code" => "+974"),
    array("name" => "Reunion",  "code" => "+262"),
    array("name" => "Romania",  "code" => "+40"),
    array("name" => "Russia",  "code" => "+7"),
    array("name" => "Rwanda",  "code" => "+250"),
    array("name" => "Samoa",  "code" => "+685"),
    array("name" => "San Marino",  "code" => "+378"),
    array("name" => "Sao Tome & Principe",  "code" => "+239"),
    array("name" => "Saudi Arabia",  "code" => "+966"),
    array("name" => "Senegal",  "code" => "+221"),
    array("name" => "Serbia",  "code" => "+381"),
    array("name" => "Seychelles",  "code" => "+248"),
    array("name" => "Sierra Leone",  "code" => "+232"),
    array("name" => "Singapore",  "code" => "+65"),
    array("name" => "Sint Maarten",  "code" => "+1"),
    array("name" => "Slovakia",  "code" => "+421"),
    array("name" => "Slovenia",  "code" => "+386"),
    array("name" => "Solomon Islands",  "code" => "+677"),
    array("name" => "Somalia",  "code" => "+252"),
    array("name" => "South Africa",  "code" => "+27"),
    array("name" => "South Korea",  "code" => "+82"),
    array("name" => "South Sudan",  "code" => "+211"),
    array("name" => "Spain",  "code" => "+34"),
    array("name" => "Sri Lanka",  "code" => "+94"),
    array("name" => "St. Barthelemy",  "code" => "+590"),
    array("name" => "St. Helena",  "code" => "+290"),
    array("name" => "St. Kitts & Nevis",  "code" => "+1"),
    array("name" => "St. Lucia",  "code" => "+1"),
    array("name" => "St. Martin",  "code" => "+590"),
    array("name" => "St. Pierre & Miquelon",  "code" => "+508"),
    array("name" => "St. Vincent & Grenadines",  "code" => "+1"),
    array("name" => "Sudan",  "code" => "+249"),
    array("name" => "Suriname",  "code" => "+597"),
    array("name" => "Svalbard & Jan Mayen",  "code" => "+47"),
    array("name" => "Sweden",  "code" => "+46"),
    array("name" => "Switzerland",  "code" => "+41"),
    array("name" => "Syria",  "code" => "+963"),
    array("name" => "Taiwan",  "code" => "+886"),
    array("name" => "Tajikistan",  "code" => "+992"),
    array("name" => "Tanzania",  "code" => "+225"),
    array("name" => "Thailand",  "code" => "+66"),
    array("name" => "Timor-Leste",  "code" => "+670"),
    array("name" => "Togo",  "code" => "+228"),
    array("name" => "Tokelau",  "code" => "+690"),
    array("name" => "Tonga",  "code" => "+676"),
    array("name" => "Trinidad & Tobago",  "code" => "+1"),
    array("name" => "Tristan da Cunha",  "code" => "+290"),
    array("name" => "Tunisia",  "code" => "+216"),
    array("name" => "Turkey",  "code" => "+90"),
    array("name" => "Turkmenistan",  "code" => "+993"),
    array("name" => "Turks & Caicos Islands",  "code" => "+1"),
    array("name" => "Tuvalu",  "code" => "+688"),
    array("name" => "U.S. Virgin Islands",  "code" => "+1"),
    array("name" => "Uganda",  "code" => "+256"),
    array("name" => "Ukraine",  "code" => "+380"),
    array("name" => "United Arab Emirates",  "code" => "+971"),
    array("name" => "United Kingdom",  "code" => "+44"),
    array("name" => "United States",  "code" => "+1"),
    array("name" => "Uruguay",  "code" => "+598"),
    array("name" => "Uzbekistan",  "code" => "+998"),
    array("name" => "Vanuatu",  "code" => "+678"),
    array("name" => "Vatican City",  "code" => "+39"),
    array("name" => "Venezuela",  "code" => "+58"),
    array("name" => "Vietnam",  "code" => "+84"),
    array("name" => "Wallis & Futuna",  "code" => "+681"),
    array("name" => "Western Sahara",  "code" => "+212"),
    array("name" => "Yemen",  "code" => "+967"),
    array("name" => "Zambia",  "code" => "+260"),
    array("name" => "Zimbabwe", "code" => "+263"),
);

define('COUNTRIES', $countries);
define('APPLICATION_STATUS', $status_codes);

function countries($country)
{
    if ($country) {
        for ($i = 0; $i < count(COUNTRIES); $i++) {
            if ($country == COUNTRIES[$i]["name"]) return json_encode(COUNTRIES[$i]);
        }
    }
    return COUNTRIES;
}
