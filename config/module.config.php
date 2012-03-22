<?php
return array(
    'soliantdoctrinequeue' => array(
    ),
    'di' => array(
        'instance' => array(
            'alias' => array(
                'zfcuser_doctrine_em'     => 'doctrine_em',
                'zfcuser_user_mapper'     => 'ZfcUserDoctrineORM\Mapper\UserDoctrine',
                'zfcuser_usermeta_mapper' => 'ZfcUserDoctrineORM\Mapper\UserMetaDoctrine',
            ),
            'orm_driver_chain' => array(
                'parameters' => array(
                    'drivers' => array(
                        'soliantdoctrinequeue_xml_driver' => array(
                            'class'          => 'Doctrine\ORM\Mapping\Driver\XmlDriver',
                            'namespace'      => 'SoliantDoctrineQueue\Entity',
                            'paths'          => array(__DIR__ . '/xml'),
                            'file_extension' => '.dcm.xml',
                        ),
                    ),
                )
            ),
        ),
    ),
);
