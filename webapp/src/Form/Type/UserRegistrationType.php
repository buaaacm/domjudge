<?php declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Team;
use App\Entity\TeamAffiliation;
use App\Entity\TeamCategory;
use App\Entity\User;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContext;

class UserRegistrationType extends AbstractType
{
    /**
     * @var DOMJudgeService
     */
    protected $dj;

    /**
     * @var ConfigurationService
     */
    protected $config;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * UserRegistrationType constructor.
     *
     * @param DOMJudgeService        $dj
     * @param ConfigurationService   $config
     * @param EntityManagerInterface $em
     */
    public function __construct(
        DOMJudgeService $dj,
        ConfigurationService $config,
        EntityManagerInterface $em
    ) {
        $this->dj     = $dj;
        $this->config = $config;
        $this->em     = $em;
    }

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('teamCategory', EntityType::class, [
                'class' => TeamCategory::class,
                'label' => '如您是北航在读学生，请选择校内，否则请选择校外',
                'mapped' => false,
                'choice_label' => 'name',
                'placeholder' => '-- 选择用户类型 --',
                'query_builder' => function (EntityRepository $er) {
                    return $er
                        ->createQueryBuilder('c')
                        ->where('c.allow_self_registration = 1')
                        ->orderBy('c.sortorder');
                },
                'attr' => [
                    'placeholder' => 'Category',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ]);

        $builder
            ->add('realName', TextType::class, [
                'label' => '请填写真实姓名和学号，否则将影响您的参赛资格',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => '姓名',
                ],
            ])
            ->add('buaaStudentNumber', TextType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => '学号',
                ],
            ]);

        $builder
            ->add('username', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => '用户名',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => '电子邮件地址',
                ],
                'constraints' => new Email(),
            ])
            ->add('teamName', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => '昵称',
                ],
                'constraints' => [
                ],
                'mapped' => false,
            ]);

        if ($this->config->get('show_affiliations')) {
            $countries = [];
            foreach (Utils::ALPHA3_COUNTRIES as $alpha3 => $country) {
                $countries["$country ($alpha3)"] = $alpha3;
            }

            $builder
                ->add('affiliation', ChoiceType::class, [
                    'choices' => [
                        '添加新学校' => 'new',
                        '选择已有学校' => 'existing',
                    ],
                    'expanded' => true,
                    'mapped' => false,
                    'label' => false,
                ])
                ->add('affiliationName', TextType::class, [
                    'label' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => '学校名称',
                    ],
                    'mapped' => false,
                ]);
            if ($this->config->get('show_flags')) {
                $builder->add('affiliationCountry', ChoiceType::class, [
                    'label' => false,
                    'required' => false,
                    'mapped' => false,
                    'choices' => $countries,
                    'placeholder' => '请选择学校所在国家/地区',
                ]);
            }
            $builder->add('existingAffiliation', EntityType::class, [
                'class' => TeamAffiliation::class,
                'label' => false,
                'required' => false,
                'mapped' => false,
                'choice_label' => 'name',
                'placeholder' => '-- 选择学校 --',
                'attr' => [
                    'placeholder' => 'Affiliation',
                ],
            ]);
        }

        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => '两次输入的密码不一致',
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => '密码',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => '重复密码',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'mapped' => false,
            ])
            ->add('shareInfo', CheckboxType::class, [
                'label' => '是否同意向赞助商提供您的信息？',
                'required' => false,
                'attr' => [
                    'checked' => 'checked',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => '注册',
                'attr' => [
                    'class' => 'btn btn-lg btn-primary btn-block',
                ],
            ]);

        $builder
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $data = $event->getData();
                $teamCategory = $this->em->getRepository(TeamCategory::class)->find((int)$data['teamCategory']);
                if ($teamCategory->getName() === '校内') {
                    $data['username'] = $data['buaaStudentNumber'];
                    $data['teamName'] = $data['username'] . '-' . $data['realName'];
                    $beihangUName = '北京航空航天大学';
                    $beihangU = $this->em->getRepository(TeamAffiliation::class)
                        ->findOneBy(['name' => $beihangUName]);
                    if ($beihangU) {
                        $beihangU = (string) $beihangU->getAffilid();
                        $data['affiliation'] = 'existing';
                        $data['existingAffiliation'] = $beihangU;
                    } else {
                        $data['affiliation'] = 'new';
                        $data['affiliationName'] = $beihangUName;
                    }
                    $event->setData($data);
                }
            });
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $validateAffiliation = function ($data, ExecutionContext $context) {
            $form = $context->getRoot();
            if ($form->get('teamCategory')->getData()->getName() === '校内') {
                $realName = $form->get('realName')->getData();
                if (empty($realName)){
                    $context->buildViolation('姓名不能为空')
                        ->atPath('realName')
                        ->addViolation();
                }
                $studentNumber = $form->get('buaaStudentNumber')->getData();
                if (empty($studentNumber)) {
                    $context->buildViolation('学号不能为空')
                        ->atPath('buaaStudentNumber')
                        ->addViolation();
                } elseif (!preg_match('/^([a-zA-Z]{2}\d{7}|\d{8})$/', $studentNumber)) {
                    $context->buildViolation('学号不合法')
                        ->atPath('buaaStudentNumber')
                        ->addViolation();
                } elseif (!preg_match('/^([A-Z]{2}\d{7}|\d{8})$/', $studentNumber)) {
                    $context->buildViolation('请使用大写字母')
                        ->atPath('buaaStudentNumber')
                        ->addViolation();
                } elseif ($this->em->getRepository(User::class)->findOneBy(['name' => $studentNumber])) {
                    $context->buildViolation('该学号已被注册')
                        ->atPath('buaaStudentNumber')
                        ->addViolation();
                }
            } else {
                $username = $form->get('username')->getData();
                if (empty($username)){
                    $context->buildViolation('用户名不能为空')
                        ->atPath('username')
                        ->addViolation();
                }
                $teamName = $form->get('teamName')->getData();
                if (empty($teamName)) {
                    $context->buildViolation('昵称不能为空')
                        ->atPath('teamName')
                        ->addViolation();
                } elseif ($this->em->getRepository(Team::class)->findOneBy(['name' => $teamName])) {
                    $context->buildViolation('此昵称已被使用')
                        ->atPath('teamName')
                        ->addViolation();
                }
            }
            if ($this->config->get('show_affiliations')) {
                /** @var Form $form */
                switch ($form->get('affiliation')->getData()) {
                    case 'new':
                        $affiliationName = $form->get('affiliationName')->getData();
                        if ($this->config->get('show_flags')) {
                            $affiliationCountry = $form->get('affiliationCountry')->getData();
                            if (empty($affiliationCountry)) {
                                $context->buildViolation('此选项不能为空')
                                    ->atPath('affiliationCountry')
                                    ->addViolation();
                            }
                        }
                        if (empty($affiliationName)) {
                            $context->buildViolation('学校名称不能为空')
                                ->atPath('affiliationName')
                                ->addViolation();
                        }
                        if ($this->em->getRepository(TeamAffiliation::class)->findOneBy(['name' => $affiliationName])) {
                            $context->buildViolation('该学校已存在，请直接选取')
                                ->atPath('affiliationName')
                                ->addViolation();
                        }
                        break;
                    case 'existing':
                        if (empty($form->get('existingAffiliation')->getData())) {
                            $context->buildViolation('此选项不能为空')
                                ->atPath('existingAffiliation')
                                ->addViolation();
                        }
                        break;
                }
            }
        };
        $resolver->setDefaults(
            [
                'data_class' => User::class,
                'constraints' => new Callback($validateAffiliation)
            ]
        );
    }
}
