import { ButtonsSection } from '@/components/DesignSystem/ButtonsSection';
import { ChartsSection } from '@/components/DesignSystem/ChartsSection';
import { FeedbackSection } from '@/components/DesignSystem/FeedbackSection';
import { FieldsSection } from '@/components/DesignSystem/FieldsSection';
import { ImagesSection } from '@/components/DesignSystem/ImagesSection';
import { LoadingSection } from '@/components/DesignSystem/LoadingSection';
import { MotionSection } from '@/components/DesignSystem/MotionSection';
import { OverlaysSection } from '@/components/DesignSystem/OverlaysSection';
import { TablesSection } from '@/components/DesignSystem/TablesSection';

/**
 * The component showcase: every shared control in its states, with no page design and nothing about the business. It is where
 * a change to the design system is looked at and where the automated accessibility and appearance checks run
 * (docs/design-system/CONTRACT.md, section 9). It exists only while developing or when a build asks for it.
 */
export function DesignSystemShowcasePage() {
    return (
        <main className="mx-auto flex max-w-3xl flex-col gap-10 p-4 pb-16 sm:p-8">
            <header>
                <h1 className="text-2xl font-semibold">Design system showcase</h1>
                <p className="text-sm text-muted-foreground">Shared components, their states and their motion. Not part of the product.</p>
            </header>
            <ButtonsSection />
            <FieldsSection />
            <FeedbackSection />
            <LoadingSection />
            <TablesSection />
            <ChartsSection />
            <ImagesSection />
            <OverlaysSection />
            <MotionSection />
        </main>
    );
}
