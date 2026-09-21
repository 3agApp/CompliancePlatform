<?php

namespace Database\Seeders;

use App\Enums\CountryOfOrigin;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProductDocumentType;
use App\Enums\ProductRequirement;
use App\Enums\SupplierConnectionStatus;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\ProductTemplate;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * A catalog big enough to judge the interface by.
 *
 * Every screen in the application reads differently with three rows than
 * with three hundred, and the pages that were designed against a handful of
 * fixtures -- the product list, the completeness column, the categories page
 * with its templates, the brand list grouped by supplier -- can only really
 * be looked at once there is something in them. This fills the database with
 * a catalog of that size.
 *
 * The data is deliberately uneven. Products are spread across every state
 * the completeness score can be in, connections across every status a trade
 * can hold, and a few families are left without templates, because a demo
 * where everything is finished shows none of the cases the interface exists
 * to handle.
 */
class DemoSeeder extends Seeder
{
    /**
     * The password every seeded account is given.
     */
    public const string PASSWORD = 'password';

    /**
     * How many products the main distributor carries.
     *
     * Raise it to see how far the pages can be pushed. The product list is
     * not paginated, so this is the knob that decides whether it needs to
     * be: every row is rendered, and every row is scored.
     */
    public const int PRODUCTS = 180;

    /**
     * Seed the demo catalog.
     */
    public function run(): void
    {
        $this->clearStoredDocuments();

        $staff = $this->createStaff();
        $suppliers = $this->createSupplierOrganizations();

        $nordwind = $this->createDistributor(
            'Nordwind Handel GmbH',
            [
                $staff['owner']->id => OrganizationRole::Owner,
                $staff['admin']->id => OrganizationRole::Admin,
                $staff['buyer']->id => OrganizationRole::Member,
                $staff['compliance']->id => OrganizationRole::Member,
            ],
        );

        $alpenwerk = $this->createDistributor(
            'Alpenwerk AG',
            [
                $staff['admin']->id => OrganizationRole::Owner,
                $staff['owner']->id => OrganizationRole::Admin,
            ],
        );

        $staff['owner']->switchOrganization($nordwind);

        $this->inviteColleague($nordwind, $staff['owner']);

        $nordwindConnections = $this->connectSuppliers($nordwind, $staff['owner'], $suppliers);
        $alpenwerkConnections = $this->connectSuppliers($alpenwerk, $staff['admin'], $suppliers, small: true);

        $this->addExtraCategories($nordwind);

        $nordwindTemplates = $this->createTemplates($nordwind);
        $alpenwerkTemplates = $this->createTemplates($alpenwerk);

        $nordwindBrands = $this->createBrands($nordwindConnections);
        $alpenwerkBrands = $this->createBrands($alpenwerkConnections);

        $this->createProducts($nordwind, $nordwindTemplates, $nordwindBrands, $nordwindConnections, $staff, self::PRODUCTS);
        $this->createProducts($alpenwerk, $alpenwerkTemplates, $alpenwerkBrands, $alpenwerkConnections, $staff, (int) round(self::PRODUCTS / 6));

        $this->report($nordwind, $alpenwerk);
    }

    /**
     * Throw away the files a previous run left on the private disk.
     *
     * The rows go with the database, but the files do not, and re-seeding
     * would otherwise pile up a directory of documents nothing points at.
     */
    protected function clearStoredDocuments(): void
    {
        Storage::disk(ProductDocument::DISK)->deleteDirectory('product-documents');
    }

    /**
     * Create the people who work at the two distributors.
     *
     * The factory gives every user an organization of their own, which is
     * exactly what this seeder does not want: the organizations here are
     * built deliberately, with the memberships they are supposed to have.
     *
     * @return array<string, User>
     */
    protected function createStaff(): array
    {
        $people = [
            'owner' => ['Test User', 'test@example.com'],
            'admin' => ['Marlene Voss', 'marlene@example.com'],
            'buyer' => ['Tobias Reuter', 'tobias@example.com'],
            'compliance' => ['Ines Kraft', 'ines@example.com'],
        ];

        $staff = [];

        foreach ($people as $key => [$name, $email]) {
            $staff[$key] = User::factory()->withoutOrganization()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::PASSWORD),
            ]);
        }

        return $staff;
    }

    /**
     * Create the supplier organizations, each with somebody to sign in as.
     *
     * Only some of a distributor's suppliers are organizations at all -- an
     * invitation that has not been claimed is a company name and an address
     * and nothing more -- so these are the ones that will be attached to an
     * active connection further down.
     *
     * @return array<string, Organization>
     */
    protected function createSupplierOrganizations(): array
    {
        $companies = [
            'brightline' => ['Shenzhen Brightline Industrial', 'Wei Zhang', 'brightline@example.com'],
            'lombarda' => ['Fabbrica Lombarda S.r.l.', 'Giulia Ferrari', 'lombarda@example.com'],
            'baltic' => ['Baltic Plastics OU', 'Kristjan Saar', 'baltic@example.com'],
            'kyoto' => ['Kyoto Precision Co.', 'Haruto Ito', 'kyoto@example.com'],
        ];

        $suppliers = [];

        foreach ($companies as $key => [$company, $contact, $email]) {
            $organization = Organization::create([
                'name' => $company,
                'type' => OrganizationType::Supplier,
            ]);

            $user = User::factory()->withoutOrganization()->create([
                'name' => $contact,
                'email' => $email,
                'password' => Hash::make(self::PASSWORD),
            ]);

            $organization->members()->attach($user, ['role' => OrganizationRole::Owner->value]);
            $user->switchOrganization($organization);

            $suppliers[$key] = $organization;
        }

        return $suppliers;
    }

    /**
     * Create a distributor and put the given people in it.
     *
     * @param  array<int, OrganizationRole>  $roles
     */
    protected function createDistributor(string $name, array $roles): Organization
    {
        $organization = Organization::create([
            'name' => $name,
            'type' => OrganizationType::Distributor,
        ]);

        foreach ($roles as $userId => $role) {
            $organization->members()->attach($userId, ['role' => $role->value]);
        }

        return $organization;
    }

    /**
     * Leave one invitation outstanding, so the members page has one to show.
     */
    protected function inviteColleague(Organization $organization, User $invitedBy): void
    {
        $organization->invitations()->create([
            'email' => 'nils@example.com',
            'role' => OrganizationRole::Member->value,
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Give the distributor a trade in every status a connection can hold.
     *
     * A pending one has no supplier organization behind it and products can
     * still be assigned to it, which is the case the rest of the application
     * has to keep working for; a revoked one keeps its products and its
     * brands but closes the supplier out.
     *
     * @param  array<string, Organization>  $suppliers
     * @return Collection<int, SupplierConnection>
     */
    protected function connectSuppliers(Organization $distributor, User $invitedBy, array $suppliers, bool $small = false): Collection
    {
        $trades = $small
            ? [
                ['Shenzhen Brightline Industrial', 'brightline@example.com', SupplierConnectionStatus::Active, $suppliers['brightline']],
                ['Tirol Kunststoff GmbH', 'kontakt@tirol-kunststoff.example', SupplierConnectionStatus::Pending, null],
            ]
            : [
                ['Shenzhen Brightline Industrial', 'brightline@example.com', SupplierConnectionStatus::Active, $suppliers['brightline']],
                ['Fabbrica Lombarda S.r.l.', 'lombarda@example.com', SupplierConnectionStatus::Active, $suppliers['lombarda']],
                ['Baltic Plastics OU', 'baltic@example.com', SupplierConnectionStatus::Active, $suppliers['baltic']],
                ['Guangdong Toyworks Ltd.', 'sales@toyworks.example', SupplierConnectionStatus::Pending, null],
                ['Ceska Filtr a.s.', 'obchod@ceska-filtr.example', SupplierConnectionStatus::Pending, null],
                ['Atlas Novelty Imports', 'hello@atlas-novelty.example', SupplierConnectionStatus::Declined, null],
                ['Kyoto Precision Co.', 'kyoto@example.com', SupplierConnectionStatus::Revoked, $suppliers['kyoto']],
            ];

        $connections = new Collection;

        foreach ($trades as [$company, $email, $status, $supplier]) {
            $connections->push($distributor->supplierConnections()->create([
                'supplier_organization_id' => $supplier?->id,
                'company_name' => $company,
                'contact_email' => $email,
                'status' => $status,
                'invited_by' => $invitedBy->id,
                'expires_at' => now()->addDays(SupplierConnection::CLAIM_EXPIRY_DAYS),
                'accepted_at' => $status === SupplierConnectionStatus::Active ? now()->subMonths(3) : null,
            ]));
        }

        return $connections;
    }

    /**
     * Add the families beyond the three a distributor starts with.
     */
    protected function addExtraCategories(Organization $organization): void
    {
        foreach (['Childcare article', 'Electrical appliance', 'Textile', 'Sports equipment'] as $name) {
            $organization->productCategories()->create(['name' => $name]);
        }
    }

    /**
     * Fit each family with the sheets a product of that kind is held to.
     *
     * Sports equipment is deliberately left without one. A family with no
     * template cannot take a product, and the product form has to say so
     * rather than let somebody fill a page in and be refused at the end --
     * which is only visible in a demo if such a family exists.
     *
     * @return Collection<int, ProductTemplate>
     */
    protected function createTemplates(Organization $organization): Collection
    {
        $sheets = [
            'Toy' => ['Full CE dossier', 'Standard check', 'Accessory only'],
            'Magnetic toy' => ['Magnet safety', 'Full CE dossier'],
            'Filter' => ['Filter performance', 'Import screening'],
            'Childcare article' => ['Full CE dossier', 'Standard check'],
            'Electrical appliance' => ['Electrical safety', 'Import screening'],
            'Textile' => ['Textile labelling', 'Accessory only'],
        ];

        $templates = new Collection;

        foreach ($organization->productCategories as $category) {
            foreach ($sheets[$category->name] ?? [] as $name) {
                $templates->push($category->templates()->create([
                    'name' => $name,
                    ...$this->requirementColumns($this->profile($name)),
                ]));
            }
        }

        return $templates;
    }

    /**
     * Get the requirements a named sheet asks for.
     *
     * @return array<int, ProductRequirement>
     */
    protected function profile(string $name): array
    {
        return match ($name) {
            'Full CE dossier' => [
                ProductRequirement::TestReport,
                ProductRequirement::DeclarationOfConformity,
                ProductRequirement::Certificate,
                ProductRequirement::SafetyImage,
                ProductRequirement::ProductImage,
                ProductRequirement::ManualOrInstructions,
                ProductRequirement::Brand,
                ProductRequirement::Ean,
                ProductRequirement::InternalArticleNumber,
                ProductRequirement::CountryOfOrigin,
                ProductRequirement::AgeGrading,
                ProductRequirement::SafetyNotice,
                ProductRequirement::WarningText,
                ProductRequirement::MaterialInformation,
            ],
            'Magnet safety' => [
                ProductRequirement::TestReport,
                ProductRequirement::DeclarationOfConformity,
                ProductRequirement::Certificate,
                ProductRequirement::SafetyImage,
                ProductRequirement::ManualOrInstructions,
                ProductRequirement::Brand,
                ProductRequirement::Ean,
                ProductRequirement::CountryOfOrigin,
                ProductRequirement::AgeGrading,
                ProductRequirement::SafetyNotice,
                ProductRequirement::WarningText,
                ProductRequirement::UsageRestrictions,
                ProductRequirement::SafetyInstructions,
            ],
            'Standard check' => [
                ProductRequirement::TestReport,
                ProductRequirement::DeclarationOfConformity,
                ProductRequirement::ProductImage,
                ProductRequirement::Ean,
                ProductRequirement::CountryOfOrigin,
                ProductRequirement::WarningText,
            ],
            'Import screening' => [
                ProductRequirement::DeclarationOfConformity,
                ProductRequirement::RegulatoryDocument,
                ProductRequirement::Ean,
                ProductRequirement::SupplierArticleNumber,
                ProductRequirement::CustomsTariffNumber,
                ProductRequirement::CountryOfOrigin,
            ],
            'Electrical safety' => [
                ProductRequirement::TestReport,
                ProductRequirement::DeclarationOfConformity,
                ProductRequirement::Certificate,
                ProductRequirement::SafetyImage,
                ProductRequirement::ManualOrInstructions,
                ProductRequirement::Ean,
                ProductRequirement::CountryOfOrigin,
                ProductRequirement::WarningText,
                ProductRequirement::SafetyInstructions,
                ProductRequirement::MaterialInformation,
            ],
            'Filter performance' => [
                ProductRequirement::TestReport,
                ProductRequirement::DeclarationOfConformity,
                ProductRequirement::Certificate,
                ProductRequirement::ProductImage,
                ProductRequirement::Ean,
                ProductRequirement::CustomsTariffNumber,
                ProductRequirement::CountryOfOrigin,
                ProductRequirement::MaterialInformation,
            ],
            'Textile labelling' => [
                ProductRequirement::DeclarationOfConformity,
                ProductRequirement::ProductImage,
                ProductRequirement::Ean,
                ProductRequirement::CountryOfOrigin,
                ProductRequirement::MaterialInformation,
                ProductRequirement::UsageRestrictions,
            ],
            default => [
                ProductRequirement::ProductImage,
                ProductRequirement::Ean,
                ProductRequirement::CountryOfOrigin,
            ],
        };
    }

    /**
     * Turn a list of requirements into the boolean columns that store them.
     *
     * @param  array<int, ProductRequirement>  $requirements
     * @return array<string, bool>
     */
    protected function requirementColumns(array $requirements): array
    {
        return [
            ...array_fill_keys(ProductRequirement::columns(), false),
            ...array_fill_keys(
                array_map(fn (ProductRequirement $requirement) => $requirement->value, $requirements),
                true,
            ),
        ];
    }

    /**
     * Name the makers each supplier carries.
     *
     * A brand belongs to one trade, so the same maker appearing under two
     * suppliers is two rows -- which the list groups by supplier and is
     * worth being able to see.
     *
     * @param  Collection<int, SupplierConnection>  $connections
     * @return Collection<int, Brand>
     */
    protected function createBrands(Collection $connections): Collection
    {
        $catalog = [
            'Shenzhen Brightline Industrial' => ['Brightline', 'Lumo Play', 'Nordika', 'Tiny Harbour'],
            'Fabbrica Lombarda S.r.l.' => ['Bellavia', 'Lombarda Casa', 'Piccolo'],
            'Baltic Plastics OU' => ['Baltica', 'Saarplast', 'Nordika'],
            'Guangdong Toyworks Ltd.' => ['Toyworks', 'Jumbo Fun', 'Rainy Day'],
            'Ceska Filtr a.s.' => ['Filtra', 'Vltava Clean'],
            'Kyoto Precision Co.' => ['Kyosei', 'Precision K'],
            'Atlas Novelty Imports' => ['Atlas Novelty'],
            'Tirol Kunststoff GmbH' => ['Tirolit', 'Alpenform'],
        ];

        $brands = new Collection;

        foreach ($connections as $connection) {
            foreach ($catalog[$connection->company_name] ?? [] as $name) {
                $brands->push($connection->brands()->create(['name' => $name]));
            }
        }

        return $brands;
    }

    /**
     * Fill the catalog.
     *
     * Every product is given a share of its sheet to have answered, drawn
     * so that the list shows the whole range at once: a few that are
     * finished, a long middle that is halfway, and a tail that has barely
     * been started. Which requirements are met is shuffled per product, so
     * two products on the same sheet are outstanding on different things.
     *
     * @param  Collection<int, ProductTemplate>  $templates
     * @param  Collection<int, Brand>  $brands
     * @param  Collection<int, SupplierConnection>  $connections
     * @param  array<string, User>  $staff
     */
    protected function createProducts(
        Organization $organization,
        Collection $templates,
        Collection $brands,
        Collection $connections,
        array $staff,
        int $count,
    ): void {
        if ($templates->isEmpty()) {
            return;
        }

        $assignable = $connections
            ->filter(fn (SupplierConnection $connection) => in_array(
                $connection->status->value,
                SupplierConnectionStatus::assignableValues(),
                true,
            ))
            ->values();

        /**
         * Every product answers to a supplier, so a distributor with none
         * to assign to has no catalog to fill.
         */
        if ($assignable->isEmpty()) {
            return;
        }

        $uploaders = [$staff['compliance']->id, $staff['buyer']->id, $staff['owner']->id];
        $documents = [];

        DB::transaction(function () use ($organization, $templates, $brands, $assignable, $uploaders, $count, &$documents) {
            for ($index = 0; $index < $count; $index++) {
                $template = $templates[$index % $templates->count()];
                $category = $template->category;

                $connection = $assignable[$index % $assignable->count()];

                $carried = $brands->where('supplier_connection_id', $connection->id)->values();

                $share = $this->completenessShare($index);
                $required = $template->requirements()->shuffle();
                $satisfy = $required->take((int) round($required->count() * $share));

                /**
                 * A sheet that asks for the maker only gets one once it has
                 * been answered, so a product reported as untouched really
                 * is. A sheet that does not ask still gets one, because a
                 * product carries a brand whether or not anyone requires it.
                 */
                $wantsBrand = ! $template->requirements()->contains(ProductRequirement::Brand)
                    || $satisfy->contains(ProductRequirement::Brand);

                $product = $organization->products()->create([
                    'name' => $this->productName($category->name, $index),
                    'supplier_connection_id' => $connection->id,
                    'product_category_id' => $category->id,
                    'product_template_id' => $template->id,
                    'brand_id' => $wantsBrand && $carried->isNotEmpty()
                        ? $carried[$index % $carried->count()]->id
                        : null,
                    ...$this->fieldValues($satisfy, $index),
                ]);

                foreach ($this->documentTypes($satisfy) as $position => $type) {
                    $documents[] = $this->documentRow($product, $type, $uploaders[($index + $position) % count($uploaders)]);
                }
            }
        });

        ProductDocument::query()->insert($documents);
    }

    /**
     * Get how much of its sheet a product has answered.
     *
     * Weighted towards the middle on purpose: a catalog where most products
     * are done says nothing about how the column reads when most of them
     * are not.
     */
    protected function completenessShare(int $index): float
    {
        return match ($index % 10) {
            0, 1 => 1.0,
            2, 3 => 0.8,
            4, 5, 6 => 0.5,
            7, 8 => 0.25,
            default => 0.0,
        };
    }

    /**
     * Answer the data requirements among the ones this product satisfies.
     *
     * Only the fields the sheet asks for are filled, so a product's score
     * and what its checklist shows outstanding line up with each other --
     * filling everything regardless would make every product look the same.
     *
     * @param  SupportCollection<int, ProductRequirement>  $satisfied
     * @return array<string, string|CountryOfOrigin>
     */
    protected function fieldValues(SupportCollection $satisfied, int $index): array
    {
        $available = [
            'ean' => (string) (4000000000000 + $index),
            'internal_article_number' => sprintf('ART-%05d', 1000 + $index),
            'supplier_article_number' => sprintf('SUP-%05d', 7000 + $index),
            'order_number' => sprintf('PO-2026-%04d', 100 + $index),
            'customs_tariff_number' => ['95030075', '84212300', '63026000', '85167100'][$index % 4],
            'country_of_origin' => [CountryOfOrigin::Germany, CountryOfOrigin::Switzerland][$index % 2],
            'age_grading' => ['0+', '3+', '6+', '8+', '14+'][$index % 5],
            'safety_notice' => 'Keep the packaging and this notice until the product has been checked on delivery.',
            'warning_text' => 'Not suitable for children under 3 years. Small parts may be swallowed.',
            'material_information' => 'ABS plastic, stainless steel fittings, water based paint. Free of phthalates.',
            'usage_restrictions' => 'Indoor use only. Not intended for continuous outdoor exposure.',
            'safety_instructions' => 'Inspect for damage before each use and replace any broken part immediately.',
            'additional_notes' => 'Replacement parts and spare filters are available directly from the manufacturer.',
        ];

        $values = [];

        foreach ($satisfied as $requirement) {
            $attribute = $requirement->productAttribute();

            if ($attribute !== null && array_key_exists($attribute, $available)) {
                $values[$attribute] = $available[$attribute];
            }
        }

        return $values;
    }

    /**
     * Get the kinds of document this product should carry.
     *
     * @param  SupportCollection<int, ProductRequirement>  $satisfied
     * @return array<int, ProductDocumentType>
     */
    protected function documentTypes(SupportCollection $satisfied): array
    {
        return $satisfied
            ->map(fn (ProductRequirement $requirement) => $requirement->documentType())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Write the file for a document and build the row that points at it.
     *
     * The files are real, so a download in the demo hands back something
     * that opens rather than a broken stream. They are also tiny: the point
     * is the shape of the list, not the bytes.
     *
     * @return array<string, mixed>
     */
    protected function documentRow(Product $product, ProductDocumentType $type, int $uploadedBy): array
    {
        $isImage = in_array($type, [ProductDocumentType::ProductImage, ProductDocumentType::SafetyImage], true);

        $contents = $isImage
            ? $this->pngBytes(160, 120, [(crc32($product->name) % 160) + 60, 140, 200])
            : $this->pdfBytes($type->label(), [
                'Product: '.$product->name,
                'Reference: '.sprintf('DOC-%06d', $product->id),
                'Issued: '.now()->subDays($product->id % 400)->toFormattedDateString(),
                'This is seeded demo content, not a real document.',
            ]);

        $extension = $isImage ? 'png' : 'pdf';
        $name = str($type->label())->slug().'-'.str($product->name)->slug().'.'.$extension;
        $path = ProductDocument::directoryFor($product).'/'.hash('sha1', $name.$product->id).'.'.$extension;

        Storage::disk(ProductDocument::DISK)->put($path, $contents);

        return [
            'product_id' => $product->id,
            'uploaded_by' => $uploadedBy,
            'type' => $type->value,
            'name' => $name,
            'path' => $path,
            'mime_type' => $isImage ? 'image/png' : 'application/pdf',
            'size' => strlen($contents),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Name a product in a way that reads like a line in a catalog.
     */
    protected function productName(string $category, int $index): string
    {
        $articles = [
            'Toy' => ['Wooden Building Blocks', 'Plush Bear', 'Race Track Set', 'Puzzle Cube', 'Marble Run', 'Stacking Rings', 'Play Kitchen Set', 'Foam Dart Blaster'],
            'Magnetic toy' => ['Magnetic Tiles', 'Magnetic Fishing Game', 'Magnetic Drawing Board', 'Magnetic Letters', 'Magnetic Construction Rods', 'Magnetic Puzzle Book'],
            'Filter' => ['Cabin Filter', 'Oil Filter', 'Water Pitcher Filter', 'HEPA Vacuum Filter', 'Coffee Machine Filter', 'Pool Cartridge Filter'],
            'Childcare article' => ['Baby Bottle', 'Soother Chain', 'Highchair Cushion', 'Changing Mat', 'Bath Thermometer', 'Teething Ring'],
            'Electrical appliance' => ['Hand Blender', 'Desk Fan', 'LED Night Light', 'Travel Kettle', 'Steam Iron', 'Coffee Grinder'],
            'Textile' => ['Cotton Bath Towel', 'Fleece Blanket', 'Bed Linen Set', 'Kitchen Apron', 'Cushion Cover', 'Microfibre Cloth Pack'],
        ];

        $variants = [
            'Toy' => ['60 pcs', '100 pcs', 'Large', 'Small', 'Twin Pack', 'Deluxe', 'Starter Set', '2026 Edition'],
            'Magnetic toy' => ['32 pcs', '60 pcs', '100 pcs', 'Starter Set', 'Deluxe', 'Travel Size'],
            'Filter' => ['Standard', 'Long Life', 'Twin Pack', 'Refill', 'Activated Carbon', 'Type A'],
            'Childcare article' => ['0-6 m', '6-18 m', 'Small', 'Large', 'Twin Pack', 'Organic'],
            'Electrical appliance' => ['230 V', 'Compact', 'Travel', 'Pro', 'Cordless', '2026 Edition'],
            'Textile' => ['50x70 cm', '70x140 cm', 'Single', 'Double', 'Twin Pack', 'Organic Cotton'],
        ];

        $bank = $articles[$category] ?? ['Article'];
        $suffixes = $variants[$category] ?? ['Standard'];

        return $bank[$index % count($bank)]
            .' '.$suffixes[intdiv($index, count($bank)) % count($suffixes)]
            .' '.sprintf('%03d', $index + 1);
    }

    /**
     * Build a small PDF that a reader will actually open.
     *
     * Written out by hand rather than pulled from a fixture so the page
     * names the product it was filed against, which makes a download in the
     * demo verifiable at a glance.
     *
     * @param  array<int, string>  $lines
     */
    protected function pdfBytes(string $title, array $lines): string
    {
        $stream = 'BT /F1 16 Tf 40 290 Td ('.$this->escapeForPdf($title).") Tj ET\n";
        $top = 255;

        foreach ($lines as $offset => $line) {
            $stream .= 'BT /F1 10 Tf 40 '.($top - $offset * 18).' Td ('.$this->escapeForPdf($line).") Tj ET\n";
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 460 340] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $startxref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n".'0000000000 65535 f '."\n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf
            .'trailer'."\n".'<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n"
            .'startxref'."\n".$startxref."\n".'%%EOF'."\n";
    }

    /**
     * Make a string safe to drop inside a PDF text object.
     */
    protected function escapeForPdf(string $text): string
    {
        return addcslashes((string) preg_replace('/[^\x20-\x7E]/', ' ', $text), '()\\');
    }

    /**
     * Build a solid colour PNG, chunk by chunk.
     *
     * @param  array{int, int, int}  $rgb
     */
    protected function pngBytes(int $width, int $height, array $rgb): string
    {
        $raw = '';

        for ($row = 0; $row < $height; $row++) {
            $raw .= chr(0).str_repeat(chr($rgb[0] & 0xFF).chr($rgb[1] & 0xFF).chr($rgb[2] & 0xFF), $width);
        }

        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', (string) gzcompress($raw, 6))
            .$chunk('IEND', '');
    }

    /**
     * Say what was made and how to get into it.
     */
    protected function report(Organization $nordwind, Organization $alpenwerk): void
    {
        $this->command->newLine();
        $this->command->info('Seeded '.Product::query()->count().' products, '
            .ProductDocument::query()->count().' documents, '
            .Brand::query()->count().' brands, '
            .ProductTemplate::query()->count().' templates across '
            .ProductCategory::query()->count().' categories.');

        $this->command->newLine();
        $this->command->table(
            ['Sign in as', 'Password', 'Sees'],
            [
                ['test@example.com', self::PASSWORD, 'Distributor, owner of '.$nordwind->name],
                ['marlene@example.com', self::PASSWORD, 'Distributor, owner of '.$alpenwerk->name],
                ['tobias@example.com', self::PASSWORD, 'Distributor, member (read only)'],
                ['brightline@example.com', self::PASSWORD, 'Supplier, connected to both distributors'],
                ['lombarda@example.com', self::PASSWORD, 'Supplier, connected to '.$nordwind->name],
                ['kyoto@example.com', self::PASSWORD, 'Supplier, revoked connection'],
            ],
        );
    }
}
