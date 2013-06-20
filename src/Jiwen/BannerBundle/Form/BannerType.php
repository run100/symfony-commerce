<?php

namespace Jiwen\BannerBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class BannerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('link')
            ->add('target')
            ->add('description')
            ->add('image')
            ->add('width')
            ->add('height')
            ->add('type')
            ->add('clicks')
            ->add('startTime')
            ->add('endTime')
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Jiwen\BannerBundle\Entity\Banner'
        ));
    }

    public function getName()
    {
        return 'jiwen_bannerbundle_bannertype';
    }
}
