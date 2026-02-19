
Voici les packages qu'on doit installer pour bien faire fonctionner un symfony API REST

composer require symfony/maker-bundle --dev
composer require orm
composer require orm-fixtures --dev 
composer require symfony/serializer-pack
composer require sensio/framework-extra-bundle
composer require symfony/validator doctrine/annotations
composer require security
composer require lexik/jwt-authentication-bundle
composer require willdurand/hateoas-bundle (ça installe automatiquement jms/serializer)
composer require nelmio/api-doc-bundle
composer require twig asset
composer require symfony/http-client

