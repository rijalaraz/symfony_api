<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class VersioningService
{
    private $defaultVersion;

    /**
     * Constructeur permettant de récupérer la requête courante (Pour extraire le champ "Accept" dans les headers)
     * ainsi que le Paramètre de configuration "default_api_version" défini dans config/services.yaml
     * @param RequestStack $requestStack
     * @param ParameterBagInterface $params
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        ParameterBagInterface $params
    ) {
        $this->defaultVersion = $params->get('default_api_version');
    }

    /**
     * Récupéreration de la version de l'API à utiliser pour la requête courante. La version est extraite
     * du champ "Accept" dans les headers de la requête. Si ce champ n'est pas présent, on retourne
     * la version par défaut définie dans les paramètres de configuration.
     * @return string
     */
    public function getVersion(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request) { 
            $acceptHeader = $request->headers->get('Accept');
            if ($acceptHeader) {
                // Extraire la version de l'en-tête Accept (ex: application/json; version=2.0)
                if (preg_match('/version=(\d+\.\d+)/', $acceptHeader, $matches)) { 
                    return $matches[1];
                }
            }
            return $this->defaultVersion;
        }
    }

}

