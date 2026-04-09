<?php

namespace Controllers;

class Index extends PublicController
{
    public function run(): void
    {
        $viewData = array(
            "hero_title" => "Comida china e internacional",
            "hero_description" => "Disfruta de nuestros platillos preparados con ingredientes frescos, excelente sabor y una experiencia agradable para toda la familia.",

            "login_url" => "index.php?page=Sec_Login",
            "register_url" => "index.php?page=Sec_Register",
            "menu_url" => "index.php?page=Productos_Hello",
            "about_url" => "index.php",

            "welcome_title" => "Bienvenidos",
            "welcome_description" => "En Restaurante Hong Kong ofrecemos una variedad de platillos de comida china e internacional, preparados con esmero para brindar calidad, sabor y una atención especial a cada cliente.",

            "schedule_title" => "Horarios",
            "schedule_description" => "Lunes a Domingo de 10:00 AM a 9:00 PM",

            "location_title" => "Ubicación",
            "location_description" => "Tocoa, Colón, Barrio Colón. Frente a Proveedor Industrial.",

            "specialties_title" => "Nuestras Especialidades",

            "specialty_1" => "Camarón Empanizado",
            "specialty_1_desc" => "Camarones crujientes preparados con una receta especial de la casa.",

            "specialty_2" => "Pollo Agridulce",
            "specialty_2_desc" => "Pollo jugoso bañado en una deliciosa salsa agridulce.",

            "specialty_3" => "Arroz HK",
            "specialty_3_desc" => "Arroz salteado al estilo oriental con un sabor especial de la casa.",

            "specialty_4" => "Chop Suey",
            "specialty_4_desc" => "Mezcla tradicional de vegetales y carnes al estilo chino.",

            "cta_title" => "Haz tu pedido ahora",
            "cta_description" => "Explora nuestro menú y disfruta de nuestros mejores platillos.",
            "cta_url" => "index.php?page=Productos_Hello",

            "SiteLinks" => array(
                "public/css/home.css"
            )
        );

        \Views\Renderer::render("index", $viewData);
    }
}
?>