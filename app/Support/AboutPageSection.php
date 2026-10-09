<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Editable texts of the About Doctor page (the expertise cards and treatment photos live in their own tables).
 * Phone and doctor identity come from Site Settings by default.
 * Values live in the `settings` table; anything not saved yet falls back to these defaults.
 */
class AboutPageSection
{
    /** Tabs whose content is a plain list, one item per line. key => [tab heading, settings key] */
    public const LIST_TABS = [
        'education' => 'Education & Qualification',
        'experience' => 'Past Experience',
        'responsibilities' => 'Current Responsibilities',
        'research' => 'Research and publications',
        'awards' => 'Awards and Recognition',
    ];

    public static function defaults(): array
    {
        $site = SiteSettings::content();

        return [
            // Banner
            'about.banner_label' => 'About Us',
            'about.banner_name' => $site['doctor_name'],
            'about.banner_qualifications' => $site['doctor_qualifications'],
            'about.banner_text' => 'Dr. Aditya Sharma is a highly experienced physician & diabetologist in Jaipur, who provides the best treatment and consultation for diabetes, hypertension, asthma, allergy, fever, anemia, and other infectious diseases. Book an appointment at {phone}.',
            'about.banner_button_text' => 'Contact Us',
            'about.banner_button_url' => '',
            'about.banner_show_phone' => '1',
            'about.banner_photo' => 'images/about/user-img-slider.png',

            // Overview tab
            'about.overview_title' => 'Overview – '.$site['doctor_name'],
            'about.overview_subtitle' => '(Consultant Physician and Diabetologist)',
            'about.overview_text' => "Dr. Aditya Sharma is a highly experienced Consultant [Physician in Jaipur](/) and Diabetologist, holding a distinguished academic background with an MBBS and MD in Internal Medicine, along with additional qualifications such as FAGE and CPCDM (RSSDI). With a profound understanding of various medical disciplines, Dr. Sharma specializes in the diagnosis and management of a wide array of health conditions. He has more than 12 years of experience.\n\nHis expertise encompasses the fields of Diabetes, Hypertension (high BP), Thyroid disorders, Fever, Infectious Diseases, Anemia, Dengue, Migraine, Asthma, Allergy, Pain, and Obesity. Dr. Sharma's commitment to providing comprehensive and compassionate care makes him a trusted healthcare professional in addressing the diverse healthcare needs of his patients. His dedication to staying abreast of the latest advancements in medical science ensures that individuals under his care receive the most up-to-date and effective treatments tailored to their specific health concerns.",
            'about.expertise_heading' => 'Expertise Services:',
            'about.overview_photo' => 'images/about/about-doctor-1.jpg',

            // Other tabs (one item per line)
            'about.education' => "MBBS - Medical College (add university name)\nM.D. (Internal Medicine) - Medical College (add university name)\nFAGE - Fellowship in Advanced Geriatric Endocrinology\nCPCDM (Certificate Programme for Clinicians in Diabetes Management) - Research Society for the Study of Diabetes in India (RSSDI)",
            'about.experience' => "Consultant Physician, Hospital name (add years)\nConsultant Physician / Assistant Professor, Medical College name (add years)\nSenior Resident, Medical College & Hospital name (add years)\nMedical Advisor & Consultant Physician, Organisation name (add years)",
            'about.responsibilities' => 'Consultant Physician and Diabetologist at '.\App\Support\SiteSettings::brandName()." Clinic, Jaipur.\nWriter for an online health educational magazine (add publication name).",
            'about.research' => "Add research paper title, authors, journal and year here.\nAdd research paper title, authors, journal and year here.\nAdd research paper title, authors, journal and year here.",
            'about.awards' => "Best research paper presentation at a physicians conference (add name and year)\nBest poster presentation at an infectious disease conference (add name and year)\nAppreciation award for efforts in COVID care during the pandemic (add institution and year)",

            // Dark call-to-action band
            'about.cta_visible' => '1',
            'about.cta_heading' => 'Looking for a Physician and diabetologist in Jaipur for consultation & treatment?',
            'about.cta_text' => 'Book your instant appointment with Dr. Aditya Sharma at '.\App\Support\SiteSettings::brandName().' Clinic, Jaipur, and get proper treatment for your health conditions.',
            'about.cta_button_text' => 'Book An Appointment',
            'about.cta_show_phone' => '1',

            // "Our Main Treatments"
            'about.treat_visible' => '1',
            'about.treat_eyebrow' => 'Treatment',
            'about.treat_heading' => 'Our Main Treatments',
            'about.treat_intro' => 'We provide the best treatment and consultation for the following diseases.',
        ];
    }

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());
        $site = SiteSettings::content();

        $lists = [];
        foreach (array_keys(self::LIST_TABS) as $key) {
            $lists[$key] = RichText::lines($v["about.$key"]);
        }

        return [
            'banner_label' => $v['about.banner_label'],
            'banner_name' => $v['about.banner_name'],
            'banner_qualifications' => $v['about.banner_qualifications'],
            'banner_text' => strtr($v['about.banner_text'], ['{phone}' => $site['phone'], '{email}' => $site['email']]),
            'banner_text_raw' => $v['about.banner_text'],
            'banner_button_text' => $v['about.banner_button_text'],
            'banner_button_url' => $v['about.banner_button_url'] !== '' ? $v['about.banner_button_url'] : route('contact'),
            'banner_show_phone' => $v['about.banner_show_phone'] === '1',
            'banner_photo' => $v['about.banner_photo'],

            'overview_title' => $v['about.overview_title'],
            'overview_subtitle' => $v['about.overview_subtitle'],
            'overview_text' => $v['about.overview_text'],
            'overview_paragraphs' => RichText::paragraphs($v['about.overview_text']),
            'expertise_heading' => $v['about.expertise_heading'],
            'overview_photo' => $v['about.overview_photo'],

            'lists' => $lists,
            'list_text' => array_map(fn ($key) => $v["about.$key"], array_combine(array_keys(self::LIST_TABS), array_keys(self::LIST_TABS))),

            'cta_visible' => $v['about.cta_visible'] === '1',
            'cta_heading' => $v['about.cta_heading'],
            'cta_text' => $v['about.cta_text'],
            'cta_button_text' => $v['about.cta_button_text'],
            'cta_show_phone' => $v['about.cta_show_phone'] === '1',

            'treat_visible' => $v['about.treat_visible'] === '1',
            'treat_eyebrow' => $v['about.treat_eyebrow'],
            'treat_heading' => $v['about.treat_heading'],
            'treat_intro' => $v['about.treat_intro'],
        ];
    }
}
