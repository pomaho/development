import { useState } from 'react';
import { WidgetHeader } from '../../_shared/uiKit';
import { BudgetSegmentContent } from './BudgetSegmentDashboard';
import { DesignerCategoryContent } from './DesignerCategoryDashboard';
import { ManagerTopupContent } from './ManagerTopupDashboard';
import { ProductGroupContent } from './ProductGroupDashboard';
import { SupplierContent } from './SupplierDashboard';

type Account = { name: string; base_domain: string };

type Period = { from: string; to: string; label: string };

type ManagerTopupLinks = { data: string; leads: string; designers: string; designerLeads: string };
type ProductGroupLinks = { data: string; leads: string };
type SupplierLinks = { data: string; leads: string };
type DesignerCategoryLinks = { data: string; leads: string };
type BudgetSegmentLinks = { data: string; leads: string };

type Props = {
    account: Account;
    period: Period;
    sections: {
        managerTopup: { links: ManagerTopupLinks } | null;
        productGroup: { links: ProductGroupLinks } | null;
        supplier: { links: SupplierLinks } | null;
        designerCategory: { links: DesignerCategoryLinks } | null;
        budgetSegment: { links: BudgetSegmentLinks } | null;
    };
};

export default function ClientDashboard({ account, period, sections }: Props) {
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [appliedFrom, setAppliedFrom] = useState(period.from);
    const [appliedTo, setAppliedTo] = useState(period.to);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setAppliedFrom(from);
        setAppliedTo(to);
    };

    return (
        <div className="min-h-screen bg-slate-100 px-3 py-5 text-gray-900 sm:px-5">
            <div className="mx-auto max-w-7xl space-y-5">
                <WidgetHeader
                    title="Отчёты Eurohome"
                    account={account}
                    period={period}
                    from={from}
                    to={to}
                    onFromChange={setFrom}
                    onToChange={setTo}
                    onSubmit={handleSubmit}
                />

                {sections.managerTopup && (
                    <ManagerTopupContent
                        account={account}
                        from={appliedFrom}
                        to={appliedTo}
                        links={sections.managerTopup.links}
                    />
                )}

                {sections.productGroup && (
                    <ProductGroupContent
                        account={account}
                        from={appliedFrom}
                        to={appliedTo}
                        links={sections.productGroup.links}
                    />
                )}

                {sections.supplier && (
                    <SupplierContent
                        account={account}
                        from={appliedFrom}
                        to={appliedTo}
                        links={sections.supplier.links}
                    />
                )}

                {sections.designerCategory && (
                    <DesignerCategoryContent
                        account={account}
                        from={appliedFrom}
                        to={appliedTo}
                        links={sections.designerCategory.links}
                    />
                )}

                {sections.budgetSegment && (
                    <BudgetSegmentContent
                        account={account}
                        from={appliedFrom}
                        to={appliedTo}
                        links={sections.budgetSegment.links}
                    />
                )}
            </div>
        </div>
    );
}
