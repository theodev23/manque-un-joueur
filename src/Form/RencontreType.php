<?php

namespace App\Form;

use App\Entity\Rencontre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RencontreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de la rencontre',
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Terrain ou lieu de rendez-vous',
            ])
            // widget: single_text permet d’afficher un champ de saisie unique pour la date et l’heure
            // input: datetime_immutable indique que la valeur soumise sera un objet DateTimeImmutable
            ->add('dateHeure', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'model_timezone' => 'UTC',
                'view_timezone' => 'Europe/Paris',
                'help' => 'Heure de Paris.',
            ])
            ->add('placesRecherchees', IntegerType::class, [
                'label' => 'Nombre de joueurs recherchés',
                'attr' => ['min' => 1],
                'help' => 'Indiquez uniquement le nombre de joueurs manquants.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Informations complémentaires',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
        ;
    }

    // data_class indique à Symfony que ce formulaire est lié à l’entité Rencontre. Ainsi, lorsque le formulaire sera soumis, Symfony pourra déterminer automatiquement une instance de Rencontre avec les données du formulaire.
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rencontre::class,
        ]);
    }
}