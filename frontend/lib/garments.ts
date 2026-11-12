import { Shirt } from "lucide-react";

export const GARMENT_CONFIG = {
    polo: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/polera-pique-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/polera-pique-white-back.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/polera-pique-white-side-left.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/polera-pique-white-side-left.png', flip: true },
    ],
    polera: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/polera-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/polera-white-back.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/polera-white-lateral-izq.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/polera-white-lateral-izq.png', flip: true },
    ],
    polera_women: [
        { id: 'front', label: 'Frente (Dama)', icon: Shirt, image: '/products/polera-white-front-women.png' },
        { id: 'back', label: 'Espalda (Dama)', icon: Shirt, image: '/products/polera-white-back-women.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/polera-white-side-left-women.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/polera-white-side-left-women.png', flip: true },
    ],
    polera_casual: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/polera-casual-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/polera-casual-white-back.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/polera-pique-white-side-left.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/polera-pique-white-side-left.png', flip: true },
    ],
    polera_pique: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/polera-pique-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/polera-pique-white-back.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/polera-pique-white-side-left.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/polera-pique-white-side-left.png', flip: true },
    ],
    polera_mangalarga: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/polera-polo-mangalarga-blanca-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/polera-polo-mangalarga-blanca-back.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/polera-mangalarga-blanca-side-left.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/polera-mangalarga-blanca-side-left.png', flip: true },
    ],
    chaqueta: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-cortaviento-front-white.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-cortaviento-back-white.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/chaqueta-cortaviento-side-left-white.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/chaqueta-cortaviento-side-left-white.png', flip: true },
    ],
    cortaviento: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-cortaviento-front-white.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-cortaviento-back-white.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/chaqueta-cortaviento-side-left-white.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/chaqueta-cortaviento-side-left-white.png', flip: true },
    ],
    geologo: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-geologo-front-white.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-geologo-back-white.png' },
    ],
    pluma: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-pluma-front-white.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-pluma-back-white.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/chaqueta-pluma-side-left-white.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/chaqueta-pluma-side-left-white.png', flip: true },
    ],
    comandante: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chqueta-comandante-front-white.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chqueta-comandante-backwhite.png' },
        { id: 'sleeve_left', label: 'Manga Izq', icon: Shirt, image: '/products/chqueta-comandante-side-left-white.png' },
        { id: 'sleeve_right', label: 'Manga Der', icon: Shirt, image: '/products/chqueta-comandante-side-left-white.png', flip: true },
    ],
    pantalon: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/pantalon-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/pantalon-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/pantalon-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/pantalon-white-side-left.png', flip: true },
    ],
    pantalon_uniforme: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/pantalon-uniforme-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/pantalon-uniforme-rescate-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/pantalon-uniforme-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/pantalon-uniforme-side-left.png', flip: true },
    ],
    quepi: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/quepi white front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/quepi white back.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/quepi white-side-derecha.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/quepi white-side-derecha.png', flip: true },
    ],
    gorro_lana: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/gorro-lana-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/gorro-lana-front.png' },
    ],
    gorro_safari: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/gorro-safari-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/gorro-safari-back.png' },
    ],
    polar_white: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/polar-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/polar-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/polar-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/polar-white-side-left.png', flip: true },
    ],
    camisa_oxford: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/camisa-oxford-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/camisa-oxford-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/camisa-oxford-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/camisa-oxford-white-side-left.png', flip: true },
    ],
    blusa_oxford_mujer: [
        { id: 'front', label: 'Frente (Mujer)', icon: Shirt, image: '/products/blusa-oxford-mujer-white-front.png' },
        { id: 'back', label: 'Espalda (Mujer)', icon: Shirt, image: '/products/blusa-oxford-mujer-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/blusa-oxford-mujer-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/blusa-oxford-mujer-white-side-left.png', flip: true },
    ],
    poleron_canguro: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/poleron-canguro-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/poleron-canguro-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/poleron-canguro-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/poleron-canguro-white-side-left.png', flip: true },
    ],
    poleron_urbano: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/poleron-urbano-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/poleron-urbano-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/poleron-urbano-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/poleron-urbano-white-side-left.png', flip: true },
    ],
    chaleco_unisex: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/polar-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/polar-white-back.png' },
    ],
    poleron_unisex: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/poleron-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/poleron-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/poleron-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/poleron-white-side-left.png', flip: true },
    ],
    parka_safari: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-geologo-front-white.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-geologo-back-white.png' },
    ],
    chaqueta_softshell: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-softshell-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-softshell-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/chaqueta-softshell-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/chaqueta-softshell-white-side-left.png', flip: true },
    ],
    chaqueta_rescate_vehicular: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-rescate-vehicular-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-rescate-vehicular-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/chaqueta-rescate-vehicular-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/chaqueta-rescate-vehicular-white-side-left.png', flip: true },
    ],
    chaqueta_rescate_forestal: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/chaqueta-rescate-forestal-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/chaqueta-rescate-forestal-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/chaqueta-rescate-forestal-side.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/chaqueta-rescate-forestal-side.png', flip: true },
    ],
    uniforme: [
        { id: 'front', label: 'Frente', icon: Shirt, image: '/products/uniforme-usar-white-front.png' },
        { id: 'back', label: 'Espalda', icon: Shirt, image: '/products/uniforme-usar-white-back.png' },
        { id: 'side_left', label: 'Lateral Izq', icon: Shirt, image: '/products/uniforme-usar-white-side-left.png' },
        { id: 'side_right', label: 'Lateral Der', icon: Shirt, image: '/products/uniforme-usar-white-side-left.png', flip: true },
    ],
};

export type GarmentType = keyof typeof GARMENT_CONFIG;

export const detectGarmentType = (productName: string, familia?: string | null, subfamilia?: string | null, initialConfigGarmentType?: string): GarmentType => {
    if (initialConfigGarmentType && Object.keys(GARMENT_CONFIG).includes(initialConfigGarmentType)) {
        return initialConfigGarmentType as GarmentType;
    }

    const textToSearch = `${productName} ${familia || ''} ${subfamilia || ''}`.toLowerCase();
    const isWomen = textToSearch.includes('mujer') || textToSearch.includes('dama');

    if (textToSearch.includes('quepi') || textToSearch.includes('jockey')) return 'quepi';
    if (textToSearch.includes('polar')) return 'polar_white';
    if (textToSearch.includes('safari') && textToSearch.includes('gorro')) return 'gorro_safari';
    if (textToSearch.includes('lana') || (textToSearch.includes('gorro') && !textToSearch.includes('quepi'))) return 'gorro_lana';
    if (textToSearch.includes('gorro')) return 'quepi';

    // Oxford detection
    if (textToSearch.includes('blusa') && textToSearch.includes('oxford')) return 'blusa_oxford_mujer';
    if (textToSearch.includes('oxford') && isWomen) return 'blusa_oxford_mujer';
    if (textToSearch.includes('camisa') && textToSearch.includes('oxford')) return 'camisa_oxford';
    if (textToSearch.includes('oxford')) return 'camisa_oxford';

    if (textToSearch.includes('canguro') || (textToSearch.includes('poleron') && textToSearch.includes('canguro'))) return 'poleron_canguro';
    if (textToSearch.includes('poleron') && textToSearch.includes('urbano')) return 'poleron_urbano';
    if (textToSearch.includes('polerón') && textToSearch.includes('urbano')) return 'poleron_urbano';
    if (textToSearch.includes('poleron') || textToSearch.includes('polerón')) return 'poleron_unisex';
    if (textToSearch.includes('softshell')) return 'chaqueta_softshell';
    if (textToSearch.includes('rescate') && (textToSearch.includes('vehicular') || textToSearch.includes('white-front'))) return 'chaqueta_rescate_vehicular';
    if (textToSearch.includes('rescate') && textToSearch.includes('forestal')) return 'chaqueta_rescate_forestal';
    if (textToSearch.includes('rescate')) return 'chaqueta_rescate_vehicular'; // Default rescue

    if (textToSearch.includes('pantalon') && textToSearch.includes('uniforme')) return 'pantalon_uniforme';
    if (textToSearch.includes('pantal')) return 'pantalon';
    if (textToSearch.includes('uniforme')) return 'uniforme';
    if (textToSearch.includes('cortaviento')) return 'cortaviento';
    if (textToSearch.includes('geologo')) return 'geologo';
    if (textToSearch.includes('pluma')) return 'pluma';
    if (textToSearch.includes('comandante')) return 'comandante';

    if ((textToSearch.includes('polera') || textToSearch.includes('polo')) &&
        (textToSearch.includes('manga larga') || textToSearch.includes('mangalarga'))) {
        return 'polera_mangalarga';
    }

    if (textToSearch.includes('polera')) {
        if (textToSearch.includes('casual')) return 'polera_casual';
        if (textToSearch.includes('pique') || textToSearch.includes('piqué')) return 'polera_pique';
        return isWomen ? 'polera_women' : 'polera';
    }

    if (textToSearch.includes('polo') || textToSearch.includes('pique') || textToSearch.includes('piqué')) return 'polo';

    if (textToSearch.includes('chaqueta') || textToSearch.includes('parka')) return 'chaqueta';

    return isWomen ? 'polera_women' : 'polera';
};
